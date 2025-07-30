<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/produit')]
#[IsGranted('ROLE_USER')]
class ProduitController extends AbstractController
{
    #[Route('/', name: 'app_produit_index', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $produitRepository, CategorieRepository $categorieRepository): Response
    {
        $categoryId = $request->query->get('category');
        
        return $this->render('produit/index.html.twig', [
            'produits' => $categoryId 
                ? $produitRepository->findBy(['categorie' => $categoryId])
                : $produitRepository->findAll(),
            'categories' => $categorieRepository->findAll(),
            'currentCategory' => $categoryId
        ]);
    }

    #[Route('/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Le produit a été créé avec succès.');
            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

   /* #[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }*/

    #[Route('/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produits_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le produit a été modifié avec succès.');
            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();

            $this->addFlash('success', 'Le produit a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/add-to-cart/{id}', name: 'app_add_to_cart', methods: ['POST'])]
    public function addToCart(Produit $produit, Request $request): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $panier = $request->getSession()->get('panier', []);

        if (isset($panier[$produit->getId()])) {
            $panier[$produit->getId()] += $quantity;
        } else {
            $panier[$produit->getId()] = $quantity;
        }

        $request->getSession()->set('panier', $panier);
        $this->addFlash('success', 'Produit ajouté au panier');
        
        return $this->redirectToRoute('app_produit_index');
    }

    #[Route('/panier', name: 'app_panier', methods: ['GET'])]
    public function panier(ProduitRepository $produitRepository, Request $request): Response
    {
        $panier = $request->getSession()->get('panier', []);
        $panierData = [];
        $total = 0;

        foreach ($panier as $id => $quantity) {
            $produit = $produitRepository->find($id);
            if ($produit) {
                $panierData[] = [
                    'produit' => $produit,
                    'quantity' => $quantity
                ];
                $total += $produit->getPrix() * $quantity;
            }
        }

        return $this->render('produit/panier.html.twig', [
            'panier' => $panierData,
            'total' => $total
        ]);
    }

    #[Route('/remove-from-cart/{id}', name: 'app_remove_from_cart', methods: ['POST'])]
    public function removeFromCart(Produit $produit, Request $request): Response
    {
        $panier = $request->getSession()->get('panier', []);

        if (isset($panier[$produit->getId()])) {
            unset($panier[$produit->getId()]);
            $request->getSession()->set('panier', $panier);
            $this->addFlash('success', 'Produit retiré du panier');
        }

        return $this->redirectToRoute('app_panier');
    }
}
#[Route('/produit/{id}/delete', name: 'app_produit_delete', methods: ['POST'])]
 function delete(
    Request $request, 
    Produit $produit, 
    EntityManagerInterface $entityManager
): Response {
    
    if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
        
        if ($produit->getImage()) {
            $imagePath = $this->getParameter('produits_directory').'/'.$produit->getImage();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $entityManager->remove($produit);
        $entityManager->flush();
        
        $this->addFlash('success', 'Le produit a été supprimé avec succès');
    } else {
        $this->addFlash('error', 'Token CSRF invalide');
    }

    return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);

    

    
}

