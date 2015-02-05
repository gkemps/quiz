<?php
namespace Quiz\Service;

use DateTime;
use Kemzy\Library\Service\AbstractService;
use Quiz\Entity\Question as QuestionEntity;
use Quiz\Entity\Category as CategoryEntity;
use Quiz\Entity\QuestionLike as QuestionLikeEntity;
use Quiz\Entity\User as UserEntity;
use Doctrine\ORM\EntityManager;
use Zend\Authentication\AuthenticationService;

class Question extends AbstractService
{
    /** @var EntityManager  */
    protected $em;

    /** @var \Doctrine\ORM\EntityRepository  */
    protected $questionRepository;

    /** @var AuthenticationService  */
    protected $authenticationService;

    /**
     * @param EntityManager $em
     * @param AuthenticationService $authenticationService
     */
    public function __construct(
        EntityManager $em,
        AuthenticationService $authenticationService
    ) {
        $this->em = $em;
        $this->questionRepository = $this->em->getRepository('Quiz\Entity\Question');
        $this->authenticationService = $authenticationService;
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getRepository()
    {
        return $this->questionRepository;
    }

    /**
     * @return \Zend\Paginator\Paginator|QuestionEntity[]
     */
    public function getQuestions()
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('q')
            ->from('\Quiz\Entity\Question', 'q')
            ->orderBy('q.dateCreated', 'DESC');

        return $this->returnPaginatedSetFromQueryBuilder($qb);
    }

    /**
     * @param CategoryEntity $category
     * @return \Zend\Paginator\Paginator|QuestionEntity[]
     */
    public function getQuestionsByCategory(CategoryEntity $category)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('q')
            ->from('\Quiz\Entity\Question', 'q')
            ->where($qb->expr()->eq('q.category', ':category'))
            ->orderBy('q.dateCreated', 'DESC');

        $qb->setParameter('category', $category);

        return $this->returnPaginatedSetFromQueryBuilder($qb);
    }

    /**
     * @param $search
     * @return \Zend\Paginator\Paginator|QuestionEntity[]
     */
    public function searchQuestions($search)
    {
        $qb = $this->em->createQueryBuilder();

        $qb->select('q')
            ->from('Quiz\Entity\Question', 'q')
            ->where($qb->expr()->orX(
                    $qb->expr()->like('q.question', ':search'),
                    $qb->expr()->like('q.answer', ':search')
                ))
            ->orderBy('q.dateCreated', 'DESC');

        $qb->setParameter('search', '%'.$search.'%');

        return $this->returnPaginatedSetFromQueryBuilder($qb);
    }

    /**
     * @param QuestionEntity $question
     * @return QuestionEntity
     */
    public function createNewQuestion(QuestionEntity $question)
    {
        $user = $this->authenticationService->getIdentity();

        $question->setDateCreated(new DateTime('now'));
        $question->setCreatedBy($user);
        $this->persist($question);

        return $question;
    }

    /**
     * @param QuestionEntity $question
     * @return QuestionEntity
     */
    public function updateQuestion(QuestionEntity $question)
    {
        $question->setDateUpdated(new DateTime('now'));
        $this->persist($question);

        return $question;
    }

    /**
     * @param QuestionEntity $question
     */
    public function likeQuestion(QuestionEntity $question)
    {
        $questionLike = $this->createQuestionLike($question);

        $question->addQuestionLike($questionLike);
        $this->persist($question);
    }

    /**
     * @param QuestionEntity $question
     * @param UserEntity $user
     */
    public function unlikeQuestion(QuestionEntity $question, UserEntity $user)
    {
        foreach ($question->getQuestionLikes() as $questionLike) {
            if ($questionLike->getQuestion()->getId() == $question->getId() &&
                $questionLike->getUser()->getId() == $user->getId()) {

                $question->removeQuestionLike($questionLike);
                $this->removeQuestionLike($questionLike);
            }
        }
    }

    protected function removeQuestionLike(QuestionLikeEntity $questionLike)
    {
        $this->em->remove($questionLike);
        $this->em->flush();
    }

    /**
     * @param QuestionEntity $question
     * @return QuestionLikeEntity
     */
    protected function createQuestionLike(QuestionEntity $question)
    {
        /** @var UserEntity $user */
        $user = $this->authenticationService->getIdentity();

        $questionLike = new QuestionLikeEntity();
        $questionLike->setQuestion($question);
        $questionLike->setUser($user);
        $questionLike->setDateCreated(new DateTime('now'));

        return $questionLike;
    }
}
