<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Video;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;
use App\Services\JwtAuth;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends AbstractController
{

    private function resjson($data){
        //Serializar datos con servicio serializer
        $json=$this->get('serializer')->serialize($data,'json');
        //Response httpfoundation
        $response=new Response();
        //Asignar contenido a la respuesta
        $response->setContent($json);
        //Indicar formato de respuesta
        $response->headers->set('Content-Type','application/json');
        //Devolver respuesta
        return $response;
    }

    public function index(): Response
    {
        $user_repo=$this->getDoctrine()->getRepository(User::class);
        $video_repo=$this->getDoctrine()->getRepository(Video::class);
        /*
        $users=$user_repo->findAll();

        foreach($users as $user){
            echo $user->getName()."\n";
            foreach($user->getVideos() as $video){
                echo $video->getUrl()."\n";
            }
        }*/

        $user=$user_repo->find(1);
        $videos=$video_repo->findAll($user);
        return $this->resjson($videos);
    }

    public function create(Request $request){
        //Recoger los datos del post
        $json=$request->get('json',null);
        //Decodificar datos
        $params=json_decode($json);
        //Respuesta por defecto
        $data = [ 
            "status"=>"success",
            "code"=>200,
            "message"=>"El usuario se ha creado correctamente",
            "params"=>$params
        ];

        //Comprobar/validar datos
        if ($json!=null){
            $name=(!empty($params->name)) ? $params->name : null;
            $surname=(!empty($params->surname)) ? $params->surname : null;
            $email=(!empty($params->email)) ? $params->email : null;
            $password=(!empty($params->password)) ? $params->password : null;

            $validator=Validation::createValidator();
            $validate_email=$validator->validate($email,[new Email()]);

            if (!empty($email)&&count($validate_email)==0&&!empty($password)&&!empty($name)&&!empty($surname)){
                //Crear usuario
                $user=new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole("ROLE_USER");
                $user->setCreatedAt(new \DateTime('now'));
                //Cifrar contraseña
                $pwd=hash('sha256',$password);
                $user->setPassword($pwd);
                //Comprobar si usuario existe
                $doctrine=$this->getDoctrine();
                $em=$doctrine->getManager();

                $user_repo=$doctrine->getRepository(User::class);
                $isset_user=$user_repo->findOneBy(array("email"=>$user->getEmail()));

                if ($isset_user==null){
                    $em->persist($user);
                    $em->flush();

                    $data = [ 
                        "status"=>"success",
                        "code"=>200,
                        "message"=>"User creado correctamente",
                        "user"=>$user
                    ];
                }else{
                    $data = [ 
                        "status"=>"error",
                        "code"=>500,
                        "message"=>"El usuario ya existe",
                        "params"=>$user
                    ];
                }
            }else{
                $data = [ 
                    "status"=>"error",
                    "code"=>500,
                    "message"=>"Validacion incorrecta",
                    "params"=>$validate_email
                ];
            }
        }else{
            $data = [ 
                "status"=>"error",
                "code"=>500,
                "message"=>"No hay datos",
                "params"=>$params
            ];
        }
            
        //Respuesta
        return $this->resjson($data);
    }


    public function login(Request $request,JwtAuth $jwtAuth){
       //Recibir datos por post
       $json=$request->get('json',null);
       $params=json_decode($json);
       //Array por defecto
        $data=[
            'status'=>'success',
            'code'=>200,
            'message'=>'El usuario no se ha podido loguear'
        ];
       //COmprobar y validar datos
        if ($json!=null){
            $email=(!empty($params->email)) ? $params->email : null;
            $password=(!empty($params->password)) ? $params->password : null;
            $getToken=(!empty($params->getToken)) ? $params->getToken : null;

            $validator=Validation::createValidator();
            $validate_email=$validator->validate($email,[new Email()]);

            if (!empty($email)&&count($validate_email)==0&&!empty($password)){
                //Cifrar la contraseña
                $pwd=hash('sha256',$password);
                //Si todo es correcto, devolvera el token u objeto
                if ($getToken){
                    $signup=$jwtAuth->signup($email,$pwd,$getToken);
                }else{
                    $signup=$jwtAuth->signup($email,$pwd);
                }
                //Si todo bien, respuesta http
                return new JsonResponse($signup);
                
            }else{
                $data=[
                    'status'=>'error',
                    'code'=>400,
                    'message'=>'Error al loguear'
                ];
            }
        }
       
       return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwtAuth){
        //Recoger cabecera de authentication
        $token=$request->headers->get("Authorization");
        //Crear metodo para comprobar si el token es correcto
        $auth=$jwtAuth->checkToken($token);

        $data=[
            'status'=>"error",
            "message"=>"Error al editar al usuario",
            "code"=>400
        ];
        //Si es correcto, editar
        if ($auth){
            //Actualizar
            //Conseguir entity manager
            $em=$this->getDoctrine()->getManager();
            //Obtener datos del usuario logueado
            $identity=$jwtAuth->checkToken($token,true);
            //conseguir el usuario a editar
            $userRepo=$this->getDoctrine()->getRepository(User::class);
            $user=$userRepo->findOneBy([
                'id'=>$identity->sub
            ]);
            
            //Recoger datos post 
            $json=$request->get('json',null);
            $params=json_decode($json);
            //Comprobar y validar datos
            if (!empty($json)){
                $name=(!empty($params->name)) ? $params->name : null;
                $surname=(!empty($params->surname)) ? $params->surname : null;
                $email=(!empty($params->email)) ? $params->email : null;

                $validator=Validation::createValidator();
                $validate_email=$validator->validate($email,[new Email()]);

                if (!empty($email)&&count($validate_email)==0&&!empty($name)&&!empty($surname)){
                    //Asignar nuevos datos al usuario
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);

                    //Comprobar duplicados
                    $issetUser=$userRepo->findBy([
                        'email'=>$email
                    ]);
                    //Guardar datos en la BD
                    if (count($issetUser)==0||$identity->email==$email){
                        $em->persist($user);
                        $em->flush();
                        $data=[
                            'status'=>"success",
                            "message"=>"Usuario Actualizado",
                            "code"=>200,
                            "user"=>$user
                        ];
                    }else{
                        $data=[
                            'status'=>"error",
                            "message"=>"Usuario duplicado",
                            "code"=>400
                        ];
                    }
                }
            }
            
        }
       
        return $this->resjson($data);
     }

     
}
