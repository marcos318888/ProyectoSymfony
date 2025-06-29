<?php

namespace App\Controller;

use App\Entity\Tarea;
use App\Repository\TareaRepository;
use App\Service\TareaManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TareaController extends AbstractController
{
    private EntityManagerInterface $em;
    private TareaRepository $tareaRepository;

    public function __construct(EntityManagerInterface $em, TareaRepository $tareaRepository)
    {
        $this->em = $em;
        $this->tareaRepository = $tareaRepository;
    }

    #[Route('/', name: 'app_listado_tarea', methods: ['GET'])]
    public function listado(): Response
    {
        $tareas = $this->tareaRepository->findAll();

        return $this->render('tarea/listado.html.twig', [
            'tareas' => $tareas,
        ]);
    }

    #[Route('/crear-tarea', name: 'app_crear_tarea', methods: ['GET', 'POST'])]
    public function crear(Request $request, TareaManager $tareaManager): Response
    {
        $tarea = new Tarea();

        if ($request->isMethod('POST')) {
            $descripcion = $request->request->get('descripcion', '');
            $tarea->setDescripcion($descripcion);

            $errores = $tareaManager->validar($tarea);

            if (count($errores) === 0) {
                $tareaManager->crear($tarea);
                $this->addFlash('success', 'Tarea creada correctamente!');
                return $this->redirectToRoute('app_listado_tarea');
            }

            foreach ($errores as $error) {
                $this->addFlash('warning', $error->getMessage());
            }
        }

        return $this->render('tarea/crear.html.twig', [
            'tarea' => $tarea,
        ]);
    }

    #[Route('/editar-tarea/{id}', name: 'app_editar_tarea', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request, TareaManager $tareaManager): Response
    {
        $tarea = $this->tareaRepository->find($id);

        if (!$tarea) {
            throw $this->createNotFoundException('Tarea no encontrada.');
        }

        if ($request->isMethod('POST')) {
            $descripcion = $request->request->get('descripcion', '');

            $tarea->setDescripcion($descripcion);

            $errores = $tareaManager->validar($tarea);

            if (count($errores) === 0) {
                // Guardamos los cambios con editar() que solo hace flush
                $tareaManager->editar($tarea);
                $this->addFlash('success', 'Tarea editada correctamente!');
                return $this->redirectToRoute('app_listado_tarea');
            }

            foreach ($errores as $error) {
                $this->addFlash('warning', $error->getMessage());
            }
        }

        return $this->render('tarea/editar.html.twig', [
            'tarea' => $tarea,
        ]);
    }


    #[Route('/eliminar-tarea/{id}', name: 'app_eliminar_tarea', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminar(Tarea $tarea, TareaManager $tareaManager): Response
    {
        $tareaManager->eliminar($tarea);

        $this->addFlash('success', 'Tarea eliminada correctamente!');
        return $this->redirectToRoute('app_listado_tarea');
    }

    #[Route('/tarea/{id}/toggle', name: 'app_toggle_tarea', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleCompletada(int $id, Request $request): JsonResponse
    {
        $tarea = $this->tareaRepository->find($id);

        if (!$tarea) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tarea no encontrada.',
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['completada'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Falta el parámetro "completada".',
            ], 400);
        }

        $tarea->setCompletada((bool)$data['completada']);

        $this->em->persist($tarea);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'completada' => $tarea->isCompletada(),
        ]);
    }
}
