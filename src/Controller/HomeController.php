<?php

namespace App\Controller;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry;

class HomeController extends AbstractController
{

    #[Route('/', name: 'home')]
    public function index(QuestionRepository $questionRepo): Response
    {
        $questions = $questionRepo->createQueryBuilder('q')
            ->leftJoin('q.author', 'a')
            ->addSelect('a')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('home/index.html.twig', [
            'questions' => $questions,
        ]);
    }
}

// 'https://randomuser.me/api/portraits/women/44.jpg'