<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CursusBundle\Controller\API;

use Claroline\CoreBundle\Entity\User;
use Claroline\CursusBundle\Entity\Cursus;
use Claroline\CursusBundle\Entity\CourseSession;
use Claroline\CursusBundle\Entity\CourseSessionUser;
use Claroline\CursusBundle\Manager\CursusManager;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\View;
use JMS\DiExtraBundle\Annotation as DI;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class CursusController extends FOSRestController
{
    private $cursusManager;
    private $formFactory;
    private $request;

    /**
     * @DI\InjectParams({
     *     "cursusManager"   = @DI\Inject("claroline.manager.cursus_manager"),
     *     "formFactory"     = @DI\Inject("form.factory"),
     *     "requestStack"    = @DI\Inject("request_stack")
     * })
     */
    public function __construct(
        CursusManager $cursusManager,
        FormFactory $formFactory,
        RequestStack $requestStack
    )
    {
        $this->cursusManager = $cursusManager;
        $this->formFactory = $formFactory;
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @View(serializerGroups={"api"})
     * @ApiDoc(
     *     description="Returns all the cursus list",
     *     views = {"cursus"}
     * )
     */
    public function getAllCursusAction()
    {
        return $this->cursusManager->getAllCursus();
    }

    /**
     * @View(serializerGroups={"api"})
     * @ApiDoc(
     *     description="Returns the cursus list",
     *     views = {"cursus"}
     * )
     */
    public function getCursusAction(Cursus $cursus)
    {
        return $this->cursusManager->getHierarchyByCursus($cursus);
    }

    /**
     * @View(serializerGroups={"api"})
     * @ApiDoc(
     *     description="Returns the course list",
     *     views = {"cursus"}
     * )
     */
    public function getCourseAction()
    {
        return $this->cursusManager->getAllCourses('', 'title', 'ASC', false);
    }

    /**
     * @View()
     * @ApiDoc(
     *     description="Register an user to a cursus",
     *     views = {"cursus"}
     * )
     * @ParamConverter("user", class="ClarolineCoreBundle:User", options={"repository_method" = "findForApi"})
     */
    public function addUserToCursusAction(User $user, Cursus $cursus)
    {
        $this->cursusManager->registerUserToCursus($cursus, $user);

        return array('success');
    }

    /**
     * @View()
     * @ApiDoc(
     *     description="Unregister an user from a cursus",
     *     views = {"cursus"}
     * )
     * @ParamConverter("user", class="ClarolineCoreBundle:User", options={"repository_method" = "findForApi"})
     */
    public function removeUserFromCursusAction(User $user, Cursus $cursus)
    {
        $this->cursusManager->unregisterUserFromCursus($cursus, $user);

        return array('success');
    }

    /**
     * @View()
     * @ApiDoc(
     *     description="Register an user to a course session",
     *     views = {"cursus"}
     * )
     * @ParamConverter("user", class="ClarolineCoreBundle:User", options={"repository_method" = "findForApi"})
     */
    public function addUserToSessionAction(User $user, CourseSession $session, $type = 0)
    {
        $this->cursusManager->registerUsersToSession($session, array($user), $type);

        return array('success');
    }

    /**
     * @View()
     * @ApiDoc(
     *     description="Unregister an user from a course session",
     *     views = {"cursus"}
     * )
     */
    public function removeUserFromSessionAction(CourseSessionUser $sessionUser)
    {
        $this->cursusManager->unregisterUsersFromSession(array($sessionUser));

        return array('success');
    }

    /**
     * @View()
     * @ApiDoc(
     *     description="Register an user to a cursus hierarchy",
     *     views = {"cursus"}
     * )
     * @ParamConverter("user", class="ClarolineCoreBundle:User", options={"repository_method" = "findForApi"})
     */
    public function addUserToCursusHierarchyAction(User $user, Cursus $cursus)
    {
        $hierarchy = array();
        $lockedHierarchy = array();
        $unlockedCursus = array();
        $allRelatedCursus = $this->cursusManager->getRelatedHierarchyByCursus($cursus);
        foreach ($allRelatedCursus as $oneCursus) {
            $parent = $oneCursus->getParent();
            $lockedHierarchy[$oneCursus->getId()] = 'blocked';

            if (is_null($parent)) {

                if (!isset($hierarchy['root'])) {
                    $hierarchy['root'] = array();
                }
                $hierarchy['root'][] = $oneCursus;
            } else {
                $parentId = $parent->getId();

                if (!isset($hierarchy[$parentId])) {
                    $hierarchy[$parentId] = array();
                }
                $hierarchy[$parentId][] = $oneCursus;
            }
        }
        $this->cursusManager->unlockedHierarchy(
            $cursus,
            $hierarchy,
            $lockedHierarchy,
            $unlockedCursus
        );
        $this->cursusManager->registerUserToMultipleCursus($unlockedCursus, $user, true, true);

        return array('success');
    }
}
