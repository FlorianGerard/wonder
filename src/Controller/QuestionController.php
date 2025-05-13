<?php

namespace App\Controller;

use App\Form\QuestionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/question', name: 'question_')]
class QuestionController extends AbstractController
{
    #[Route('/ask', name: 'form')]
    public function index(Request $request): Response
    {

        $formQuestion = $this->createForm(QuestionType::class);

        $formQuestion->handleRequest($request);

        if ($formQuestion->isSubmitted() && $formQuestion->isValid()) {
            dd($formQuestion->getData());
        }

        return $this->render('question/index.html.twig', [
            'form' => $formQuestion->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Request $request, string $id): Response
    {

        $question =   [
            'title' => 'Je suis une super question',
            'content' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Tempora, adipisci. Libero aperiam dolores excepturi, quidem maxime accusantium inventore. Illum, odio dolores! Ullam omnis veritatis laborum, animi inventore nostrum optio voluptates.',
            'rating' => 20,
            'author' => [
                'name' => 'Jean Dupont',
                'avatar' => 'https://randomuser.me/api/portraits/men/52.jpg'
            ],
            'nbrOfResponse' => 15
        ];

        return $this->render('question/show.html.twig', [
            'question' => $question,
        ]);
    }
}
