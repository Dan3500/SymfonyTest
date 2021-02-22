<?php
namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth{

    public $manager;
    private $key;

    public function __construct($manager){
        $this->manager=$manager;
        $this->key="Clave Para Hashear la COntreaseña";
    }
     
    public function signup($email,$password,$getToken=null){
        //COmprobar si existe el user
        $user=$this->manager->getRepository(User::class)->findOneBy([
            'email'=>$email,
            'password'=>$password
        ]);
        //SI existe, generar token
        if (is_object($user)){
            $token=[
                'sub'=>$user->getId(),
                'name'=>$user->getName(),
                'surname'=>$user->getSurname(),
                'email'=>$user->getEmail(),
                'iat'=>time(),
                'exp'=>time()+(7*24*60*60)
            ];

            $jwt=JWT::encode($token,$this->key,'HS256');
            //Comprobar el token , condicion
            if (!empty($getToken)){
                $data=$jwt;
            }else{
                $decoded=JWT::decode($jwt,$this->key,['HS256']);
                $data=$decoded;
            }
        }else{
            $data=[
                "status"=>"error",
                "code"=>400,
                "message"=>"Error con el token"
            ];
        }
        //Devolver datos
        return $data;
    }

    public function checkToken($jwt,$identity=false){
        $auth=false;
        try{
            $decoded=JWT::decode($jwt,$this->key,['HS256']);
        }catch(\UnexpectedValueException $e){
            $auth=false;
        }catch(\DomainException $e){
            $auth=false;
        }
        
        if (isset($decoded)&&!empty($decoded)&&is_object($decoded)&&isset($decoded->sub)){
            $auth=true;
        }
        if ($identity==true){
            return $decoded;
        }else{
            return $auth;
        }
     }
}