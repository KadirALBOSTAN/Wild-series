<?php
// src/Controller/ProgramController.php
namespace App\Controller;

use App\Entity\Program;
use App\Entity\Season;
use App\Entity\Episode;
use App\Entity\Comment;
use App\Form\CommentType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ProgramType;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Slugify;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;


/**
 * @Route("/programs", name="program_")
 */
Class ProgramController extends AbstractController
{
    /**
     * @Route("/", name="index")
     * @return Response A response instance
     */
    public function index(): Response
    {
        $programs = $this->getDoctrine()
            ->getRepository(Program::class)
            ->findAll();

        return $this->render(
            'program/index.html.twig',
            ['programs' => $programs]
        );
    }

    /**
     * The controller for the category add form
     * Display the form or deal with it
     *
     * @Route("/new", name="new", methods={"GET","POST"})
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */

    public function new(Request $request, Slugify $slugify, MailerInterface $mailer) : Response
    {
        // Create a new Program Object
        $program = new Program();
        // Create the associated Form
        $form = $this->createForm(ProgramType::class, $program);
        // Get data from HTTP request
        $form->handleRequest($request);
        // Was the form submitted ?
        if ($form->isSubmitted() && $form->isValid()) {
            // handle data, in example, an insert into database
            // redirection

            $entityManager = $this->getDoctrine()->getManager();
            // Persist Category Object

            $slug = $slugify->generate($program->getTitle());
            $program->setSlug($slug);

            $entityManager->persist($program);
            // Flush the persisted object
            $entityManager->flush();

            $email = (new Email())
                ->from($this->getParameter('mailer_from'))
                ->to('d0850397c7-13f7d1@inbox.mailtrap.io')
                ->subject('Une nouvelle série vient d\'être publiée !')
                ->html($this->renderView('program/newProgramEmail.html.twig', ['program' => $program]));

            $mailer->send($email);

            return $this->redirectToRoute('program_index');
        }

        return $this->render('program/new.html.twig', ["form" => $form->createView()]);
    }

    /**
     * Getting a program by id
     *
     * @Route("/{slug}", name="show")
     *
     */
    public function show(Program $program): Response
    {
        $seasons = $program->getSeasons();

        if (!$program) {
            throw $this->createNotFoundException(
                'No program with id : '.$program.' found in program\'s table.'
            );
        }
        return $this->render('program/show.html.twig',
            [
                'program'=> $program,
                'seasons' => $seasons
            ]);
    }

    /**
     * @Route("/{slug}/season/{season}", name="season_show")
     */
    public function showSeason(Program $program, Season $season): Response
    {
        $episodes = $season->getEpisodes();

        return $this->render('program/season_show.html.twig', [
            'season' => $season,
            'program' => $program,
            'episodes' => $episodes
        ]);
    }

    /**
     * @Route("/{slug}/seasons/{seasonId}/episode/{episodeSlug}", name="episode_show")
     * @ParamConverter("program", class="App\Entity\Program", options={"mapping": {"slug": "slug"}})
     * @ParamConverter("season", class="App\Entity\Season", options={"mapping": {"seasonId": "id"}})
     * @ParamConverter("episode", class="App\Entity\Episode", options={"mapping": {"episodeSlug": "slug"}})
     * @param Program $program
     * @param Season $season
     * @param Episode $episodes
     * @param Request $request
     * @return Response
     */
    public function showEpisode(Program $program, Season $season, Episode $episode, Request $request): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);
        $user=$this->getUser();
        $comment->setEpisode($episode);
        $comment->setAuthor($user);


        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();
            return $this->redirectToRoute('comment_index');
        }

        $comments=$this->getDoctrine()
            ->getRepository(Comment::class)
            ->findBy(['episode'=>$episode]);

        return $this->render('program/episode_show.html.twig', [
            'program' => $program,
            'season' => $season,
            'episode' => $episode,
            'comments'=> $comments,
            'form'=> $form->createView()
        ]);
    }
}
