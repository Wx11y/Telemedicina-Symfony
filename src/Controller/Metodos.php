<?php
namespace App\Controller;
// src/Controller/PedidosLogin.php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Consulta;
use App\Entity\Especialidades;
use App\Entity\Medico;
use App\Entity\Mensaje;
use App\Entity\Usuario;
use App\Entity\Valoran;


class Metodos extends AbstractController{

    /**
     * @Route("/remedico", name="remedico")
     */
    public function remedico() {
        var_dump($_FILES);
        var_dump($_POST);die;
        if(!isset($_FILES['cv'])){
            if($this->isGranted('ROLE_USER')){
                if($especialidades = $this->comprobarMedico()){
                    return $this->render('registroMedico.html.twig', array('especialidades'=> $especialidades, 'error'=>true));
                }
            }
        }
        
        if(isset($_FILES)){
            if($_FILES['cv']['name'] == ''){
                if($this->isGranted('ROLE_USER')){
                    if($especialidades = $this->comprobarMedico()){
                        return $this->render('registroMedico.html.twig', array('especialidades'=> $especialidades, 'error'=>true));
                    }
                } 
            }
            if(isset($_POST)){
                $mensaje = new Medico();
                $mensaje->setNumCol($_POST['colegiado']);
                $mensaje->setEspecialidad($_POST['especialidad']);
                $mensaje->setHospital($_POST['hospital']);
                $mensaje->setCV($_FILES['cv']['name']);
                $mensaje->setsuario($this->getId());
                $entityManager->persist($mensaje);
            }
        }
        return $this->redirectToRoute('perfil');
    }

    /**
     * @Route("/datosMedico", name="datosMedico")
     */
    public function datosMedico() {

        if($this->isGranted('ROLE_USER')){
            $entityManager = $this->getDoctrine()->getManager();
            $especialidades = $entityManager->getRepository(Especialidades::class)->findAll();
            return $this->render('registroMedico.html.twig',array('especialidades' => $especialidades));
        }
        return $this->redirectToRoute('ctrl_login');
    }

    private function comprobarMedico(){
        $usuario = $this->getUser();
        $dominio = substr($usuario->getCorreo(),strpos($usuario->getCorreo(), '@')+1);
        if($dominio == 'comem.es'){
            $entityManager = $this->getDoctrine()->getManager();
            $medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $usuario));
            if(!$medico){
                $especialidades = $entityManager->getRepository(Especialidades::class)->findAll();
                return $especialidades;
            }
        }
        return null;
    }

    /**
     * @Route("/bandeja", name="bandeja")
     */
    public function bandeja() {

        if($this->isGranted('ROLE_USER')){
            if($especialidades = $this->comprobarMedico()){
            return $this->render('registroMedico.html.twig', array('especialidades'=> $especialidades));
            }
            return $this->render('bandeja.html.twig');
        }
        return $this->redirectToRoute('ctrl_login');
    }

    /**
     * @Route("/medicos", name="medicos")
     */
    public function medicos() {
        $entityManager = $this->getDoctrine()->getManager();
        $especialidades = $entityManager->getRepository(Especialidades::class)->findAll();
        return $this->render('medicos.html.twig',array('especialidades' => $especialidades, 'error'=>false));
    }

    /**
     * @Route("/perfil", name="perfil")
     */
    public function perfil() {
        if($this->isGranted('ROLE_USER')){
            if($especialidades = $this->comprobarMedico()){
                return $this->render('registroMedico.html.twig', array('especialidades'=> $especialidades, 'error'=>false));
            }
        $entityManager = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $usuario));

        if(!$usuario->getFoto()){
            $usuario = null;
        }
        if(!$medico){
            $medico = null;
            return $this->render('perfil.html.twig',array('usuario' => $usuario,'medico' => $medico));
        }else{
            $filesystem = new Filesystem();
            $directorio = dirname(__FILE__);
            $rutaF = $directorio.'/../../public/Usuarios/u'.$this->getUser()->getId().'/cv/'.'curriculum.pdf';
            $rutaP = 'Usuarios/u'.$this->getUser()->getId().'/cv/'.'curriculum.pdf';
            $ruta = $filesystem->exists($rutaF);
            if(!$ruta){
                $rutaP= null;
            }
            $especialidades = $entityManager->getRepository(Especialidades::class)->findAll();
            return $this->render('perfil.html.twig',array('usuario' => $usuario,'medico' => $medico,'especialidades'=>$especialidades, 'cv' =>$rutaP));
        }
        }
        return $this->redirectToRoute('bandeja');        
    }
   
    /**
     * @Route("/bandeja/nueva_consulta", name="formularioConsulta")
     */
    public function mostrarFormulario() {
        if($this->isGranted('ROLE_USER')){
            if($especialidades = $this->comprobarMedico()){
                return $this->render('registroMedico.html.twig', array('especialidades'=> $especialidades));
                }
        $entityManager = $this->getDoctrine()->getManager();
        $medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $this->getUser()));
        if(!$medico){
            $especialidades = $entityManager->getRepository(Especialidades::class)->findAll();
            return $this->render('formularioConsulta.html.twig',array('especialidades' => $especialidades));
        }
        }
        return $this->redirectToRoute('bandeja');
    }
    
    /**
     * @Route("/bandeja/consulta/{consulta}", name="consulta")
     */
    public function cargarConsulta($consulta = null) {
        if($this->isGranted('ROLE_USER') && $consulta){
        $entityManager = $this->getDoctrine()->getManager();
        $consulta = $entityManager->getRepository(Consulta::class)->findOneBy(array('codigo'=>$consulta));
        if($consulta){
            if(($consulta->getUsuario()->getId() == $this->getUser()->getId()) || ($consulta->getMedico()->getUsuario()->getId() == $this->getUser()->getId())){
                if($medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $this->getUser()))){
                    $nombre = $consulta->getUsuario()->getNombre();
                    $apellido = $consulta->getUsuario()->getApellido();
                    $foto = $consulta->getUsuario()->getFoto();
                }else{
                    $nombre = $consulta->getMedico()->getUsuario()->getNombre();
                    $apellido = $consulta->getMedico()->getUsuario()->getApellido();
                    $foto = $consulta->getMedico()->getUsuario()->getFoto();
                }
                $mensajes = $entityManager->getRepository(Mensaje::class)->findBy(array('codigo_consulta'=>$consulta));

                if($mensajes[count($mensajes)-1]->getUsuario()->getId() != $this->getUser()->getId()){
                    $consulta->setLeido(1);
                    $entityManager->flush();
                }

                foreach ($mensajes as $mensaje) {
                    $mensaje->setMensaje(str_replace("\n", '</p><p>', $mensaje->getMensaje()));
                    if($mensaje->getAdjunto()){
                        $directory = dirname(__FILE__);
                        $id = $mensaje->getUsuario()->getId();
                        $codconsulta = "con" . $mensaje->getConsulta()->getCodigo();
                        $codmensaje = $mensaje->getCodigo();
                        $idconsulta = $mensaje->getConsulta()->getCodigo();
                        $ficheroname = $mensaje->getAdjunto();
                        $absolute_url = $this->full_url($_SERVER);

                        $borrar = "bandeja/consulta/$idconsulta";
                        $ruta = substr($absolute_url, 0, -1*strlen($borrar));
                        $directorio = $ruta . "Usuarios/u$id/$codconsulta/men$codmensaje";
                        $mensaje->setRuta($directorio . "/$ficheroname");
                        
                        $extension = pathinfo($mensaje->getAdjunto(), PATHINFO_EXTENSION);
                        $extension = strtolower($extension);
                        if($extension == 'jpg' || $extension == 'png' || $extension == 'gif' || $extension == 'jpeg' || $extension == 'svg' || $extension == 'tiff' || $extension == 'ico'){
                           $mensaje->setFecha(null); 
                        }
                    }
                }
                $datos = $nombre . " " . $apellido . ": " . $consulta->getAsunto();
                $valoracion = $entityManager->getRepository(Valoran::class)->findOneBy(array('codigo_consulta'=>$consulta->getCodigo()));
                if($valoracion){
                    $valoracion = $valoracion->getValoracion();
                }else{
                    $valoracion = 0;
                } 
                return $this->render('consulta.html.twig',array('datos' => $datos, 'mensajes' => $mensajes,'valoracion' => $valoracion, 'consulta' => $consulta, 'foto' => $foto));
            }
        }
        
        }
        return $this->redirectToRoute('bandeja');
    }

    /**
     * @Route("/bandeja/crearconsulta", name="crear_consulta", methods={"POST"})
     */
    public function crearConsulta() {
        if(isset($_POST['asunto'] ) && isset($_POST['mensaje']) && isset($_POST['medicos']) && $_POST['asunto'] && $_POST['mensaje'] && $_POST['medicos']){
            //var_dump($_POST['medicos']);die;
            foreach($_POST['medicos'] as $medico){
                $entityManager = $this->getDoctrine()->getManager();
               
                //Creamos la consulta
                $consulta_nueva = new Consulta();
                $consulta_nueva->setAsunto($_POST['asunto']);
                $consulta_nueva->setLeido(0);
                $consulta_nueva->setCompletado(0);
                $consulta_nueva->setUsuario($this->getUser());
                $consulta_nueva->setMedico($entityManager->find(Medico::class,$medico));
                $entityManager->persist($consulta_nueva);
                
                //Creamos el mensaje
                $mensaje = new Mensaje();
                $mensaje->setMensaje($_POST['mensaje']);
                $mensaje->setConsulta($consulta_nueva);
                $mensaje->setUsuario($this->getUser());
                $entityManager->persist($mensaje);

                $entityManager->flush();
                if(isset($_FILES['fichero']) && $_FILES['fichero']){
                    $usuario = $this->getUser();


                    $mensaje->setAdjunto($_FILES['fichero']['name']);
                    $cv = $_FILES['fichero']['tmp_name'];
                    $nombre = $_FILES['fichero']['name'];
                    $filesystem = new Filesystem();
                        $directory = dirname(__FILE__);
                        $id = $usuario->getId();
                        $consulta = "con" . $mensaje->getConsulta()->getCodigo();
                        $codmensaje = $mensaje->getCodigo();
                        $directorio = $directory . "/../../public/Usuarios/u$id";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta/men$codmensaje";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $extension = pathinfo($nombre, PATHINFO_EXTENSION);
                        $mover = move_uploaded_file($cv, $directorio . "/$nombre");
                }
                
                $entityManager->flush();
                $consulta = $consulta_nueva->getCodigo();
            }
            
        }
        return $this->redirectToRoute('consulta', array('consulta' => $consulta));
    }
     /**
     * @Route("/bandeja/enviarMensaje", name="enviarMensaje", methods={"POST"})
     */
    public function enviarMensaje() {
        if(isset($_POST['mensaje'] ) && isset($_FILES['fichero'])){
            if($_POST['mensaje'] || $_FILES['fichero']){
                $usuario = $this->getUser();
                $entityManager = $this->getDoctrine()->getManager();
                $mensaje = new Mensaje();
                $mensaje->setConsulta($entityManager->find(Consulta::class,$_GET['consulta']));
                $mensaje->setUsuario($entityManager->find(Usuario::class,$this->getUser()->getId()));
                $entityManager->persist($mensaje);
                $entityManager->flush();

                $mensaje->getConsulta()->setFecha($mensaje->getFecha());
                $mensaje->getConsulta()->setLeido(0);

                if($_POST['mensaje']){
                    $mensaje->setMensaje($_POST['mensaje']);
                }
                if($_FILES['fichero']){
                    $mensaje->setAdjunto($_FILES['fichero']['name']);
                    $cv = $_FILES['fichero']['tmp_name'];
                    $nombre = $_FILES['fichero']['name'];
                    if($cv){
                        $filesystem = new Filesystem();
                        $directory = dirname(__FILE__);
                        $id = $usuario->getId();
                        $consulta = "con" . $mensaje->getConsulta()->getCodigo();
                        $codmensaje = $mensaje->getCodigo();
                        $directorio = $directory . "/../../public/Usuarios/u$id";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $directorio = $directory . "/../../public/Usuarios/u$id/$consulta/men$codmensaje";
                        $ruta = $filesystem->exists($directorio);
                        if(!$ruta){
                            $filesystem->mkdir(
                                Path::normalize($directorio),
                            );
                        }
                        $extension = pathinfo($nombre, PATHINFO_EXTENSION);
                        $mover = move_uploaded_file($cv, $directorio . "/$nombre");
                }
                }
                $entityManager->flush();
            }   
        }
        return $this->redirectToRoute('consulta',array('consulta' => $_GET['consulta']));
    }

     /**
     * @Route("/finconsulta", name="finconsulta")
     */
    public function cerrarConsulta() {
            if($this->isGranted('ROLE_USER')){
                $entityManager = $this->getDoctrine()->getManager();
                $consulta = $entityManager->getRepository(Consulta::class)->findOneBy(array('codigo'=>$_GET['consulta']));
                if($consulta){
                    if(($consulta->getUsuario()->getId() == $this->getUser()->getId()) || ($consulta->getMedico()->getUsuario()->getId() == $this->getUser()->getId())){
                        $consulta->setCompletado(1);
                        $entityManager->flush();
                      }
                }
                }
        return $this->redirectToRoute('consulta',array('consulta' => $_GET['consulta']));
    }

    /**
     * @Route("/puntuarconsulta", name="puntuarconsulta")
     */
    public function puntuarConsulta() {
        if($this->isGranted('ROLE_USER')){
            $entityManager = $this->getDoctrine()->getManager();
            $consulta = $entityManager->getRepository(Consulta::class)->findOneBy(array('codigo'=>$_GET['consulta']));
            if($consulta){
                if(($consulta->getUsuario()->getId() == $this->getUser()->getId())){
                    if(isset($_POST['puntos']) && $_POST['puntos']){
                        if(1 <= $_POST['puntos'] && $_POST['puntos'] <= 5){
                            $valoracion = new Valoran();
                            $valoracion->setValoracion($_POST['puntos']);
                            $valoracion->setCodigo($consulta->getCodigo());
                            $valoracion->setNumCol($consulta->getMedico()->getNumCol());
                            $valoracion->setIdUsuario($this->getUser()->getId());
                            $entityManager->persist($valoracion);
                            $entityManager->flush();
                        }
                    }
                  }
            }
            }
    return $this->redirectToRoute('consulta',array('consulta' => $_GET['consulta']));
}

     /**
     * @Route("/bandeja/actualizarDatos", name="actualizarDatos")
     */
    public function actualizarDatos() {
        if($this->isGranted('ROLE_USER')){
            $entityManager = $this->getDoctrine()->getManager();
            $usuario = $this->getUser();
            $medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $usuario));
            
            $usuario->setNombre($_POST['nombre']);
            $usuario->setApellido($_POST['apellido']);

            if($medico){
                $especialidad = $entityManager->getRepository(Especialidades::class)->findOneBy(array('codigo'=> $_POST['especialidad']));
                $medico->setEspecialidad($especialidad);
                $medico->setHospital($_POST['hospital']);
                $cv = $_FILES['cv']['tmp_name'];
                $nombre = $_FILES['cv']['name'];
                $directorio = dirname(__FILE__);

                if($cv){
                    $filesystem = new Filesystem();
                    $directorio = dirname(__FILE__);
                    $ruta = $filesystem->exists($directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv/');
                    if($ruta){
                        //Mete el curriculum en la carpeta
                        $rutaF = $directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv/'.'curriculum.pdf';
                        $mover = move_uploaded_file($cv, $directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv/'.'curriculum.pdf');
                    }else{
                        $this->comprobarcarpetaCv($usuario);
                        //Mete el curriculum en la carpeta
                        $rutaF = $directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv/'.'curriculum.pdf';
                        $mover = move_uploaded_file($cv, $directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv/'.'curriculum.pdf');
                    }
                }
            }
            //En la tabla usuario
            
            if($_FILES['foto']['tmp_name']){
                $stream = fopen($_FILES['foto']['tmp_name'],'rb');
                $usuario->setFoto(base64_encode(stream_get_contents($stream)));
            }
          
            $entityManager->flush();
        }
        return $this->redirectToRoute('perfil');
    }
    
    //LO QUE NO SEA AJAX VA ARRIBA
    //AQUI EMPEZAMOS AJAX

    /**
     * @Route("/recogerConsultas", name="recogerConsultas")
     */
    public function recogerConsultas() {
        $datos = array();
        if($this->isGranted('ROLE_USER')){
            
            $entityManager = $this->getDoctrine()->getManager();
            
            $medico = $entityManager->getRepository(Medico::class)->findOneBy(array('usuario'=> $this->getUser()));
            if($medico){
                $consultas = $entityManager->getRepository(Consulta::class)->findBy(array('medico'=>$medico),array('fecha' => 'DESC'));
            }else{
                $consultas = $entityManager->getRepository(Consulta::class)->findBy(array('usuario'=>$this->getUser()), array('fecha' => 'DESC'));
            }
            foreach ($consultas as $consulta) {

            $mensajes = $entityManager->getRepository(Mensaje::class)->findBy(array('codigo_consulta'=>$consulta));

            if(!$consulta->getLeido() && $mensajes[count($mensajes)-1]->getUsuario()->getId() != $this->getUser()->getId()){
                $consulta->setLeido(0);
            }else{
                $consulta->setLeido(1);
            }
            # code...
            if($medico){
                $nombre = $consulta->getUsuario()->getNombre();
                $apellido = $consulta->getUsuario()->getApellido();
            }else{
                $nombre = $consulta->getMedico()->getUsuario()->getNombre();
                $apellido = $consulta->getMedico()->getUsuario()->getApellido();
            }
            array_push($datos, array($consulta->getCodigo(),$consulta->getAsunto(),$nombre, $apellido,$consulta->getLeido()));
        }
        
        return new JsonResponse($datos);
    }
    
    }
    /**
     * @Route("/medicosPorEspecialidad/{codigo}", name="medicosPorEspecialidad", methods={"GET"})
     */
    public function medicosPorEspecialidad($codigo=0) {
        $entityManager = $this->getDoctrine()->getManager();
        if($codigo){
            $medicos = $entityManager->getRepository(Medico::class)->findBy(array('especialidad'=> $entityManager->getRepository(Especialidades::class)->findOneBy(array('codigo'=>$codigo))));
        }else{
            $medicos = $entityManager->getRepository(Medico::class)->findAll();
        }
        $datos = array();
        for($i = 0;$i<count($medicos);$i++){

            $valoraciones = $entityManager->getRepository(Valoran::class)->findBy(array('num_medico'=> $medicos[$i]->getNumCol()));
            $total = 0;
            foreach($valoraciones as $valoracion){
               $total += $valoracion->getValoracion();
            }
            if($total) $total /= count($valoraciones);

            if($medicos[$i]->getUsuario()->getFoto()){
                $foto = $medicos[$i]->getUsuario()->getFoto();
            }else{
                $foto = null;
            }

            array_push($datos, array($medicos[$i]->getUsuario()->getId(),$medicos[$i]->getNumCol(),$medicos[$i]->getUsuario()->getNombre(),$medicos[$i]->getUsuario()->getApellido(),$medicos[$i]->getHospital(),$total,$foto));
        }	
        return new JsonResponse($datos);
    
    }







    
//Funciones

    //Funcion para crear la carpeta de usuario y dentro de el la de chats
    private function comprobarcarpetaMensaje($usuario){
        try {
            $filesystem = new Filesystem();
            $finder = new Finder();
            $directorio = dirname(__FILE__);
            $filesystem->exists($directorio.'/../../public/Usuarios/u'.$usuario->getId());
            if(is_dir($directorio.'/../../public/Usuarios/u'.$usuario->getId()) && is_dir($directorio.'/../../public/Usuarios/u'.$usuario->getId().'/chats')){
                    echo null;
            }else{
                $filesystem->mkdir(
                    Path::normalize($directorio.'/../../public/Usuarios/u'.$usuario->getId()),
                );
                $filesystem->mkdir(
                    Path::normalize($directorio.'/../../public/Usuarios/u'.$usuario->getId().'/chats'),
                );
            } 
        }
        catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your directory at ".$exception->getPath();
        }
    }
    //Funcion para crear la carpeta de usuario y dentro de el la de cv
    private function comprobarcarpetaCv($usuario){
        try {
            $filesystem = new Filesystem();
            $finder = new Finder();
            $directorio = dirname(__FILE__);
            $filesystem->exists($directorio.'/../../public/Usuarios/u'.$usuario->getId());
            if(is_dir($directorio.'/../../public/Usuarios/u'.$usuario->getId()) && is_dir($directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv')){
                    echo null;
            }else{
                $filesystem->mkdir(
                    Path::normalize($directorio.'/../../public/Usuarios/u'.$usuario->getId()),
                );
                $filesystem->mkdir(
                    Path::normalize($directorio.'/../../public/Usuarios/u'.$usuario->getId().'/cv'),
                );
            } 
           }
           catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your directory at ".$exception->getPath();
        }
    }

   private function url_origin($s, $use_forwarded_host=false) {

        $ssl = ( ! empty($s['HTTPS']) && $s['HTTPS'] == 'on' ) ? true:false;
        $sp = strtolower( $s['SERVER_PROTOCOL'] );
        $protocol = substr( $sp, 0, strpos( $sp, '/'  )) . ( ( $ssl ) ? 's' : '' );
      
        $port = $s['SERVER_PORT'];
        $port = ( ( ! $ssl && $port == '80' ) || ( $ssl && $port=='443' ) ) ? '' : ':' . $port;
        
        $host = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
        $host = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
      
        return $protocol . '://' . $host;
      
      }
      
     private function full_url( $s, $use_forwarded_host=false ) {
        return $this->url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
      }
}

