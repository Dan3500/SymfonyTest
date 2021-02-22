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
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;

class VideoController extends AbstractController
{
    
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

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

    public function create(Request $request,JwtAuth $jwtAuth,$id=null){
        $data=[
            "status"=>"error",
            "code"=>400,
            "message"=>"Error al publicar el video"
        ];

        //Recoger token
        $token=$request->headers->get("Authorization",null);
        //Comprobar si es correcto
        $authCheck=$jwtAuth->checkToken($token);

        if ($authCheck){
            //Recoger datos Post
            $json=$request->get('json',null);
            $params=json_decode($json);
            //Recoger objeto user identificado
            $identity=$jwtAuth->checkToken($token,true);
            //Validar datos
            if (!empty($json)){
                $userId=($identity->sub!=null)?$identity->sub:null;
                $title=(!empty($params->title))?$params->title:null;
                $description=(!empty($params->description))?$params->description:null;
                $url=(!empty($params->url))?$params->url:null;

                if (!empty($userId)&&!empty($title)){
                    //Guardar nuevo video favorito en la base de datos
                    $em=$this->getDoctrine()->getManager();
                    $user=$this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id'=>$userId
                    ]);

                    
                    if ($id==null){
                        //Crear y guardar video
                        $video=new Video();
                        $video->setUser($user);
                        $video->setTittle($title);
                        $video->setDescription($description);
                        $video->setStatus("normal");
                        $video->setUrl($url);
                        $createdAt=new \DateTime('now');
                        $video->setCreatedAt($createdAt);
                        $video->setupdatedAt($createdAt);

                        $em->persist($video);
                        $em->flush();

                        $data=[
                            "status"=>"success",
                            "code"=>200,
                            "message"=>"Video guardado",
                            "video"=>$video
                        ];
                    }else{
                        $video=$this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            "id"=>$id,
                            "user"=>$identity->sub
                        ]);
                        if ($video&&is_object($video)){
                            $video->setTittle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                            $createdAt=new \DateTime('now');
                            $video->setupdatedAt($createdAt);

                            $em->persist($video);
                            $em->flush();

                            $data=[
                                "status"=>"success",
                                "code"=>200,
                                "message"=>"Video actualizado",
                                "video"=>$video
                            ];
                        }else{
                            $data=[
                                "status"=>"error",
                                "code"=>404,
                                "message"=>"No existe el video que quieres actualizar"
                            ];
                        }
                    } 
                }
            }
        }else{
            $data=[
                "status"=>"error",
                "code"=>400,
                "message"=>"Error,usuario no valido"
            ];
        }
        //Devolver respuesta
        return $this->resjson($data);
    }

    public function videos(Request $request,JwtAuth $jwtAuth, PaginatorInterface $paginator){
        $data=[
            "status"=>"error",
            "code"=>400,
            "message"=>"No se han podido listar los videos"
        ];

        //Recoger cabecera
        $token=$request->headers->get("Authorization",null);
        //Comprobar token
        $authCheck=$jwtAuth->checkToken($token);
        //Si es valido, conseguir identidad del user
        if ($authCheck){
            //COnfigurar bandle paginacion
            $identity=$jwtAuth->checkToken($token,true);

            $data=[
                "status"=>"success",
                "code"=>200,
                "message"=>"Usuario correcto"
            ];

            $em=$this->getDoctrine()->getManager();
            //COnsulta paginar
            $dql="SELECT v FROM App\Entity\Video v WHERE v.user={$identity->sub} ORDER BY v.id DESC";
            $query=$em->createQuery($dql);
            //Recoger page URL
            $page=$request->query->getInt('page',1);
            $items_per_page=5;
            //Invocar paginacion
            $pagination=$paginator->paginate($query,$page,$items_per_page);
            $total=$pagination->getTotalItemCount();
            //Preparar array devolver
            $data=[
                "status"=>"success",
                "code"=>200,
                "message"=>"Videos listados",
                "total_items"=>$total,
                "page_actual"=>$page,
                "items_per_page"=>$items_per_page,
                "total_pages"=>ceil($total/$items_per_page),
                "videos"=>$pagination,
                "user_id"=>$identity->sub
            ];
        }else{
            $data=[
                "status"=>"error",
                "code"=>400,
                "message"=>"Usuario no valido"
            ];
        }
        //Devolver respuesta
        return $this->resjson($data);
    }

    public function video(Request $request,JwtAuth $jwtAuth, $id=null){
        $data=[
            "status"=>"error",
            "code"=>400,
            "message"=>"No existe el video",
            "id"=>$id
        ];
        //Sacar el token y comprobar
        $token=$request->headers->get("Authorization",null);
        $authCheck=$jwtAuth->checkToken($token);
        //Si es valido, conseguir identidad del user
        if ($authCheck){
            //Sacar el usuario
            $identity=$jwtAuth->checkToken($token,true);
            //Sacar el objeto del video
            $video=$this->getDoctrine()->getRepository(Video::class)->findOneBy([
                "id"=>$id
            ]);
            //Comprobar si existe el video y es del usuario
            if ($video&&is_object($video)&&$identity->sub==$video->getUser()->getId()){
                $data=[
                    "status"=>"success",
                    "code"=>200,
                    "message"=>"Video encontrado",
                    "video"=>$video
                ];
            }
        }else{
            $data=[
                "status"=>"error",
                "code"=>400,
                "message"=>"Usuario no valido"
            ];
        }
    
        //Devolver la respuesta
        return $this->resjson($data);
    }

    public function remove(Request $request,JwtAuth $jwtAuth, $id=null){
        $data=[
            "status"=>"error",
            "code"=>400,
            "message"=>"Video no encontrado"
        ];

        //Recoger token
        $token=$request->headers->get("Authorization",null);
        $authCheck=$jwtAuth->checkToken($token);
        //Si es valido, conseguir identidad del user
        if ($authCheck){
            //Sacar el usuario
            $identity=$jwtAuth->checkToken($token,true);
            $doctrine=$this->getDoctrine();
            $em=$doctrine->getManager();
            //Sacar el objeto del video
            $video=$this->getDoctrine()->getRepository(Video::class)->findOneBy([
                "id"=>$id
            ]);
            //Comprobar si existe el video y es del usuario
            if ($video&&is_object($video)&&$identity->sub==$video->getUser()->getId()){
                $em->remove($video);
                $em->flush();

                $data=[
                    "status"=>"success",
                    "code"=>200,
                    "message"=>"Video borrado",
                    "video"=>$video
                ];
            }
        }else{
            $data=[
                "status"=>"error",
                "code"=>400,
                "message"=>"Usuario no valido"
            ];
        }

        //Devolver la respuesta
        return $this->resjson($data);
    }
}
