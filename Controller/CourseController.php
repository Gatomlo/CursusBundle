<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CursusBundle\Controller;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Manager\MailManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\ToolManager;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CursusBundle\Entity\Course;
use Claroline\CursusBundle\Entity\CourseRegistrationQueue;
use Claroline\CursusBundle\Entity\CourseSession;
use Claroline\CursusBundle\Entity\CourseSessionRegistrationQueue;
use Claroline\CursusBundle\Entity\CourseSessionGroup;
use Claroline\CursusBundle\Entity\CourseSessionUser;
use Claroline\CursusBundle\Entity\Cursus;
use Claroline\CursusBundle\Entity\CursusDisplayedWord;
use Claroline\CursusBundle\Form\CourseQueuedUserTransferType;
use Claroline\CursusBundle\Form\CourseSessionEditType;
use Claroline\CursusBundle\Form\CourseSessionType;
use Claroline\CursusBundle\Form\CourseType;
use Claroline\CursusBundle\Manager\CursusManager;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("canOpenAdminTool('claroline_cursus_tool')")
 */
class CourseController extends Controller
{
    private $cursusManager;
    private $formFactory;
    private $mailManager;
    private $request;
    private $roleManager;
    private $router;
    private $toolManager;
    private $translator;
    private $workspaceManager;

    /**
     * @DI\InjectParams({
     *     "cursusManager"    = @DI\Inject("claroline.manager.cursus_manager"),
     *     "formFactory"      = @DI\Inject("form.factory"),
     *     "mailManager"      = @DI\Inject("claroline.manager.mail_manager"),
     *     "requestStack"     = @DI\Inject("request_stack"),
     *     "roleManager"      = @DI\Inject("claroline.manager.role_manager"),
     *     "router"           = @DI\Inject("router"),
     *     "toolManager"      = @DI\Inject("claroline.manager.tool_manager"),
     *     "translator"       = @DI\Inject("translator"),
     *     "workspaceManager" = @DI\Inject("claroline.manager.workspace_manager")
     * })
     */
    public function __construct(
        CursusManager $cursusManager,
        FormFactory $formFactory,
        MailManager $mailManager,
        RequestStack $requestStack,
        RoleManager $roleManager,
        RouterInterface $router,
        ToolManager $toolManager,
        TranslatorInterface $translator,
        WorkspaceManager $workspaceManager
    )
    {
        $this->cursusManager = $cursusManager;
        $this->formFactory = $formFactory;
        $this->mailManager = $mailManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->roleManager = $roleManager;
        $this->router = $router;
        $this->toolManager = $toolManager;
        $this->translator = $translator;
        $this->workspaceManager = $workspaceManager;
    }

    /**
     * @EXT\Route(
     *     "/tool/course/index/page/{page}/max/{max}/ordered/by/{orderedBy}/order/{order}/search/{search}",
     *     name="claro_cursus_tool_course_index",
     *     defaults={"page"=1, "search"="", "max"=50, "orderedBy"="title","order"="ASC"},
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     */
    public function cursusToolCourseIndexAction(
        $search = '',
        $page = 1,
        $max = 50,
        $orderedBy = 'title',
        $order = 'ASC'
    )
    {
        $displayedWords = array();

        foreach (CursusDisplayedWord::$defaultKey as $key) {
            $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
        }
        $courses = $search === '' ?
            $this->cursusManager->getAllCourses($orderedBy, $order, $page, $max) :
            $this->cursusManager->getSearchedCourses($search, $orderedBy, $order, $page, $max);

        return array(
            'defaultWords' => CursusDisplayedWord::$defaultKey,
            'displayedWords' => $displayedWords,
            'type' => 'course',
            'courses' => $courses,
            'search' => $search,
            'page' => $page,
            'max' => $max,
            'orderedBy' => $orderedBy,
            'order' => $order
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/create/form",
     *     name="claro_cursus_course_create_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseCreateForm.html.twig")
     */
    public function courseCreateFormAction(User $authenticatedUser)
    {
        $displayedWords = array();

        foreach (CursusDisplayedWord::$defaultKey as $key) {
            $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
        }
        $form = $this->formFactory->create(new CourseType($authenticatedUser), new Course());

        return array(
            'form' => $form->createView(),
            'displayedWords' => $displayedWords,
            'type' => 'course'
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/create",
     *     name="claro_cursus_course_create",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseCreateForm.html.twig")
     */
    public function courseCreateAction(User $authenticatedUser)
    {
        $course = new Course();
        $form = $this->formFactory->create(new CourseType($authenticatedUser), $course);
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $icon = $form->get('icon')->getData();

            if (!is_null($icon)) {
                $hashName = $this->cursusManager->saveIcon($icon);
                $course->setIcon($hashName);
            }
            $this->cursusManager->persistCourse($course);

            $message = $this->translator->trans(
                'course_creation_confirm_msg' ,
                array(),
                'cursus'
            );
            $session = $this->request->getSession();
            $session->getFlashBag()->add('success', $message);

            return new RedirectResponse(
                $this->router->generate('claro_cursus_tool_course_index')
            );
        } else {
            $displayedWords = array();

            foreach (CursusDisplayedWord::$defaultKey as $key) {
                $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
            }

            return array(
                'form' => $form->createView(),
                'displayedWords' => $displayedWords,
                'type' => 'course'
            );
        }
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/edit/form/source/{source}",
     *     name="claro_cursus_course_edit_form",
     *     defaults={"source"=0},
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     *
     * @param Course $course
     * @param int $source
     */
    public function courseEditFormAction(
        Course $course,
        User $authenticatedUser,
        $source = 0
    )
    {
        $displayedWords = array();

        foreach (CursusDisplayedWord::$defaultKey as $key) {
            $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
        }
        $form = $this->formFactory->create(
            new CourseType($authenticatedUser),
            $course
        );

        return array(
            'form' => $form->createView(),
            'course' => $course,
            'displayedWords' => $displayedWords,
            'type' => 'course',
            'source' => $source
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/edit/source/{source}",
     *     name="claro_cursus_course_edit",
     *     defaults={"source"=0},
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseEditForm.html.twig")
     *
     * @param Course $course
     * @param int $source
     */
    public function courseEditAction(
        Course $course,
        User $authenticatedUser,
        $source = 0
    )
    {
        $form = $this->formFactory->create(
            new CourseType($authenticatedUser),
            $course
        );
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $icon = $form->get('icon')->getData();

            if (!is_null($icon)) {
                $hashName = $this->cursusManager->changeIcon($course, $icon);
                $course->setIcon($hashName);
            }
            $this->cursusManager->persistCourse($course);

            $message = $this->translator->trans(
                'course_edition_confirm_msg' ,
                array(),
                'cursus'
            );
            $session = $this->request->getSession();
            $session->getFlashBag()->add('success', $message);
            $route = $source === 0 ?
                $this->router->generate('claro_cursus_tool_course_index') :
                $this->router->generate(
                    'claro_cursus_course_management',
                    array('course' => $course->getId())
                );
            return new RedirectResponse($route);
        } else {
            $displayedWords = array();

            foreach (CursusDisplayedWord::$defaultKey as $key) {
                $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
            }

            return array(
                'form' => $form->createView(),
                'course' => $course,
                'displayedWords' => $displayedWords,
                'type' => 'course',
                'source' => $source
            );
        }
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/delete",
     *     name="claro_cursus_course_delete",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param Course $course
     */
    public function courseDeleteAction(Course $course)
    {
        $this->cursusManager->deleteCourse($course);

        $message = $this->translator->trans(
            'course_deletion_confirm_msg' ,
            array(),
            'cursus'
        );
        $session = $this->request->getSession();
        $session->getFlashBag()->add('success', $message);

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "/cursus/course/{course}/description/display",
     *     name="claro_cursus_course_display_description",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseDescriptionDisplayModal.html.twig")
     *
     * @param Course $course
     */
    public function courseDescriptionDisplayAction(Course $course)
    {
        return array('description' => $course->getDescription());
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/management",
     *     name="claro_cursus_course_management",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     *
     * @param Cursus $cursus
     *
     */
    public function courseManagementAction(Course $course)
    {
        $displayedWords = array();

        foreach (CursusDisplayedWord::$defaultKey as $key) {
            $displayedWords[$key] = $this->cursusManager->getDisplayedWord($key);
        }
        $sessions = $this->cursusManager->getSessionsByCourse($course);
        $sessionsTab = array();

        foreach ($sessions as $session) {
            $status = $session->getSessionStatus();

            if (!isset($sessionsTab[$status])) {
                $sessionsTab[$status] = array();
            }
            $sessionsTab[$status][] = $session;
        }
        $queues = $this->cursusManager->getCourseQueuesByCourse($course);

        return array(
            'defaultWords' => CursusDisplayedWord::$defaultKey,
            'displayedWords' => $displayedWords,
            'type' => 'course',
            'course' => $course,
            'sessionsTab' => $sessionsTab,
            'queues' => $queues
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/session/create/form",
     *     name="claro_cursus_course_session_create_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseSessionCreateModalForm.html.twig")
     */
    public function courseSessionCreateFormAction(Course $course)
    {
        $form = $this->formFactory->create(new CourseSessionType());

        return array('form' => $form->createView(), 'course' => $course);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/{course}/session/create",
     *     name="claro_cursus_course_session_create",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseSessionCreateModalForm.html.twig")
     */
    public function courseSessionCreateAction(Course $course, User $authenticatedUser)
    {
        $session = new CourseSession();
        $form = $this->formFactory->create(new CourseSessionType(), $session);
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $creationDate = new \DateTime();
            $session->setCreationDate($creationDate);
            $session->setCourse($course);
            $session->setPublicRegistration($course->getPublicRegistration());
            $session->setPublicUnregistration($course->getPublicUnregistration());
            $session->setRegistrationValidation($course->getRegistrationValidation());
            $workspace = $this->cursusManager->generateWorkspace(
                $course,
                $session,
                $authenticatedUser
            );
            $session->setWorkspace($workspace);
            $learnerRole = $this->cursusManager->generateRoleForSession(
                $workspace,
                $course->getLearnerRoleName(),
                0
            );
            $tutorRole = $this->cursusManager->generateRoleForSession(
                $workspace,
                $course->getTutorRoleName(),
                1
            );
            $session->setLearnerRole($learnerRole);
            $session->setTutorRole($tutorRole);
            $this->cursusManager->persistCourseSession($session);

            return new JsonResponse('success', 200);
        } else {

            return array('form' => $form->createView(), 'course' => $course);
        }
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/edit/form",
     *     name="claro_cursus_course_session_edit_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseSessionEditModalForm.html.twig")
     */
    public function courseSessionEditFormAction(CourseSession $session)
    {
        $form = $this->formFactory->create(new CourseSessionEditType($session), $session);

        return array('form' => $form->createView(), 'session' => $session);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/edit",
     *     name="claro_cursus_course_session_edit",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseSessionEditModalForm.html.twig")
     */
    public function courseSessionEditAction(CourseSession $session)
    {
        $form = $this->formFactory->create(new CourseSessionEditType($session), $session);
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $this->cursusManager->persistCourseSession($session);

            return new JsonResponse('success', 200);
        } else {

            return array('form' => $form->createView(), 'session' => $session);
        }
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/delete/with/workspace/{mode}",
     *     name="claro_cursus_course_session_delete",
     *     options={"expose"=true}
     * )
     * @EXT\Method("DELETE")
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     */
    public function courseSessionDeleteAction(CourseSession $session, $mode)
    {
        $withWorkspace = (intval($mode) === 1);
        $this->cursusManager->deleteCourseSession($session, $withWorkspace);

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/view/management",
     *     name="claro_cursus_course_session_view_management",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     *
     * @param CourseSession $session
     */
    public function courseSessionViewManagementAction(CourseSession $session)
    {
        $sessionUsers = $this->cursusManager->getSessionUsersBySession($session);
        $sessionGroups = $this->cursusManager->getSessionGroupsBySession($session);
        $queues = $this->cursusManager->getSessionQueuesBySession($session);
        $learners = array();
        $tutors = array();
        $learnersGroups = array();
        $tutorsGroups = array();

        foreach ($sessionUsers as $sessionUser) {

            if ($sessionUser->getUserType() === 0) {
                $learners[] = $sessionUser;
            } elseif ($sessionUser->getUserType() === 1) {
                $tutors[] = $sessionUser;
            }
        }

        foreach ($sessionGroups as $sessionGroup) {

            if ($sessionGroup->getGroupType() === 0) {
                $learnersGroups[] = $sessionGroup;
            } elseif ($sessionGroup->getGroupType() === 1) {
                $tutorsGroups[] = $sessionGroup;
            }
        }

        return array(
            'session' => $session,
            'learners' => $learners,
            'tutors' => $tutors,
            'learnersGroups' => $learnersGroups,
            'tutorsGroups' => $tutorsGroups,
            'queues' => $queues
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/registration/unregistered/users/{userType}/list/page/{page}/max/{max}/ordered/by/{orderedBy}/order/{order}/search/{search}",
     *     name="claro_cursus_course_session_registration_unregistered_users_list",
     *     defaults={"userType"=0, "page"=1, "search"="", "max"=50, "orderedBy"="firstName","order"="ASC"},
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     *
     * Displays the list of users who are not registered to the session.
     *
     * @param CourseSession $session
     * @param integer $userType
     * @param string  $search
     * @param integer $page
     * @param integer $max
     * @param string  $orderedBy
     * @param string  $order
     */
    public function courseSessionRegistrationUnregisteredUsersListAction(
        CourseSession $session,
        $userType = 0,
        $search = '',
        $page = 1,
        $max = 50,
        $orderedBy = 'firstName',
        $order = 'ASC'
    )
    {
        $users = $search === '' ?
            $this->cursusManager->getUnregisteredUsersBySession(
                $session,
                $userType,
                $orderedBy,
                $order,
                $page,
                $max
            ) :
            $this->cursusManager->getSearchedUnregisteredUsersBySession(
                $session,
                $userType,
                $search,
                $orderedBy,
                $order,
                $page,
                $max
            );

        return array(
            'session' => $session,
            'userType' => $userType,
            'users' => $users,
            'search' => $search,
            'max' => $max,
            'orderedBy' => $orderedBy,
            'order' => $order
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/register/user/{user}/type/{userType}",
     *     name="claro_cursus_course_session_register_user",
     *     options = {"expose"=true}
     * )
     * @EXT\Method("POST")
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSession $session
     * @param User $user
     * @param int $userType
     */
    public function courseSessionUserRegisterAction(
        CourseSession $session,
        User $user,
        $userType
    )
    {
        $results = array();
        $sessionUsers = $this->cursusManager->registerUsersToSession(
            $session,
            array($user),
            $userType
        );

        foreach ($sessionUsers as $sessionUser) {
            $user = $sessionUser->getUser();
            $results[] = array(
                'id' => $sessionUser->getId(),
                'user_type' => $sessionUser->getUserType(),
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'user_first_name' => $user->getFirstName(),
                'user_last_name' => $user->getLastName()
            );
        }

        return new JsonResponse($results, 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/unregister/user/{sessionUser}",
     *     name="claro_cursus_course_session_unregister_user",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSessionUser $sessionUser
     */
    public function courseSessionUserUnregisterAction(CourseSessionUser $sessionUser)
    {
        $this->cursusManager->unregisterUsersFromSession(array($sessionUser));

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/unregister/group/{sessionGroup}",
     *     name="claro_cursus_course_session_unregister_group",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSessionGroup $sessionGroup
     */
    public function courseSessionGroupUnregisterAction(CourseSessionGroup $sessionGroup)
    {
        $this->cursusManager->unregisterGroupFromSession($sessionGroup);

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/confirmation/mail/send",
     *     name="claro_cursus_course_session_confirmation_mail_send",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSession $session
     */
    public function courseSessionConfirmationMailSendAction(CourseSession $session)
    {
        $confirmationEmail = $this->cursusManager->getConfirmationEmail();

        if (!is_null($confirmationEmail)) {
            $users = array();
            $sessionUsers = $session->getSessionUsers();

            foreach ($sessionUsers as $sessionUser) {

                if ($sessionUser->getUserType() === 0) {
                    $users[] = $sessionUser->getUser();
                }
            }
            $course = $session->getCourse();
            $startDate = $session->getStartDate();
            $endDate = $session->getEndDate();
            $title = $confirmationEmail->getTitle();
            $content = $confirmationEmail->getContent();
            $title = str_replace('%course%', $course->getTitle(), $title);
            $content = str_replace('%course%', $course->getTitle(), $content);
            $title = str_replace('%session%', $session->getName(), $title);
            $content = str_replace('%session%', $session->getName(), $content);

            if (!is_null($startDate)) {
                $title = str_replace('%start_date%', $session->getStartDate(), $title);
                $content = str_replace('%start_date%', $session->getStartDate(), $content);
            }

            if (!is_null($endDate)) {
                $title = str_replace('%end_date%', $session->getEndDate(), $title);
                $content = str_replace('%end_date%', $session->getEndDate(), $content);
            }
            $this->mailManager->send($title, $content, $users);
        }

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/{session}/user/{user}/confirmation/mail/send",
     *     name="claro_cursus_course_session_user_confirmation_mail_send",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param User $user
     */
    public function courseSessionUserConfirmationMailSendAction(
        CourseSession $session,
        User $user
    )
    {
        $confirmationEmail = $this->cursusManager->getConfirmationEmail();

        if (!is_null($confirmationEmail)) {
            $course = $session->getCourse();
            $startDate = $session->getStartDate();
            $endDate = $session->getEndDate();
            $title = $confirmationEmail->getTitle();
            $content = $confirmationEmail->getContent();
            $title = str_replace('%course%', $course->getTitle(), $title);
            $content = str_replace('%course%', $course->getTitle(), $content);
            $title = str_replace('%session%', $session->getName(), $title);
            $content = str_replace('%session%', $session->getName(), $content);

            if (!is_null($startDate)) {
                $title = str_replace('%start_date%', $session->getStartDate()->format('d-m-Y'), $title);
                $content = str_replace('%start_date%', $session->getStartDate()->format('d-m-Y'), $content);
            }

            if (!is_null($endDate)) {
                $title = str_replace('%end_date%', $session->getEndDate()->format('d-m-Y'), $title);
                $content = str_replace('%end_date%', $session->getEndDate()->format('d-m-Y'), $content);
            }
            $this->mailManager->send($title, $content, array($user));
        }

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/registration/queue/{queue}/accept",
     *     name="claro_cursus_course_session_user_registration_accept",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSession $session
     * @param User $user
     */
    public function courseSessionUserRegistrationAcceptAction(
        CourseSessionRegistrationQueue $queue
    )
    {
        $user = $queue->getUser();
        $session = $queue->getSession();
        $this->cursusManager->registerUsersToSession($session, array($user), 0);
        $this->cursusManager->deleteSessionQueue($queue);

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/session/registration/queue/{queue}/decline",
     *     name="claro_cursus_course_session_user_registration_decline",
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     *
     * @param CourseSession $session
     * @param User $user
     */
    public function courseSessionUserRegistrationDeclineAction(
        CourseSessionRegistrationQueue $queue
    )
    {
        $this->cursusManager->deleteSessionQueue($queue);

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "cursus/course/queue/{queue}/user/transfer/form",
     *     name="claro_cursus_course_queued_user_transfer_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseQueuedUserTransferModalForm.html.twig")
     */
    public function courseQueuedUserTransferFormAction(CourseRegistrationQueue $queue)
    {
        $course = $queue->getCourse();
        $form = $this->formFactory->create(new CourseQueuedUserTransferType($course));

        return array(
            'form' => $form->createView(),
            'queue' => $queue
        );
    }

    /**
     * @EXT\Route(
     *     "cursus/course/queue/{queue}/user/transfer",
     *     name="claro_cursus_course_queued_user_transfer",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineCursusBundle:Course:courseQueuedUserTransferModalForm.html.twig")
     */
    public function courseQueuedUserTransferAction(CourseRegistrationQueue $queue)
    {
        $queueId = $queue->getId();
        $course = $queue->getCourse();
        $form = $this->formFactory->create(new CourseQueuedUserTransferType($course));
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $session = $form->get('session')->getData();
            $this->cursusManager->transferQueuedUserToSession($queue, $session);

            return new JsonResponse($queueId, 200);
        } else {

            return array(
                'form' => $form->createView(),
                'queue' => $queue
            );
        }
    }
}
