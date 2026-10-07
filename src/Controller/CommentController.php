<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\CommentType;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommentController extends AbstractController
{
    #[Route('/comment/{id}/edit', name: 'comment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, CommentRepository $commentRepository, Request $request): Response
    {
        $comment = $commentRepository->find($id) ?? throw $this->createNotFoundException();
        $form = $this->createForm(CommentType::class, [
            'author' => $comment->author,
            'content' => $comment->content
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $commentRepository->update($id, $data['author'], $data['content']);
            return $this->redirectToRoute('product_show', ['id' => $comment->productId], Response::HTTP_SEE_OTHER);
        }
        return $this->render('comment/edit.html.twig', [
            'comment' => $comment,
            'form' => $form
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, CommentRepository $commentRepository, Request $request): Response
    {
        $comment = $commentRepository->find($id) ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('delete-comment-' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $commentRepository->delete($id);
        return $this->redirectToRoute('product_show', ['id' => $comment->productId], Response::HTTP_SEE_OTHER);
    }
}
