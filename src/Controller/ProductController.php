<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Service\ProductApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/', name: 'product_list')]
    #[Route('/products', name: 'product_list_alias')]
    public function list(ProductApiClient $apiClient): Response
    {
        return $this->render('product/list.html.twig', [
            'products' => $apiClient->findAll(),
        ]);
    }

    #[Route('/products/{id}', name: 'product_show', requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, ProductApiClient $apiClient, CommentRepository $commentRepository): Response
    {
        $product = $apiClient->findById($id);
        $form = $this->createForm(CommentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $commentRepository->add($product->id, $data['author'], $data['content']);
            return $this->redirectToRoute('product_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        return $this->render('product/show.html.twig', [
            'product' => $product,
            'comments' => $commentRepository->findByProduct($product->id),
            'form' => $form,
        ]);
    }
}
