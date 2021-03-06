<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CursusBundle\Entity;

use Claroline\CoreBundle\Entity\Model\WorkspaceModel;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="Claroline\CursusBundle\Repository\CourseRepository")
 * @ORM\Table(name="claro_cursusbundle_course")
 * @DoctrineAssert\UniqueEntity("code")
 */
class Course
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"api"})
     */
    protected $id;
    
    /**
     * @ORM\Column(unique=true)
     * @Assert\NotBlank()
     * @Groups({"api"})
     * @SerializedName("code")
     */
    protected $code;
    
    /**
     * @ORM\Column()
     * @Assert\NotBlank()
     * @Groups({"api"})
     * @SerializedName("title")
     */
    protected $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"api"})
     * @SerializedName("description")
     */
    protected $description;
    
    /**
     * @ORM\Column(name="public_registration", type="boolean")
     * @Groups({"api"})
     * @SerializedName("publicRegistration")
     */
    protected $publicRegistration = false;

    /**
     * @ORM\Column(name="public_unregistration", type="boolean")
     * @Groups({"api"})
     * @SerializedName("publicUnregistration")
     */
    protected $publicUnregistration = false;

    /**
     * @ORM\Column(name="registration_validation", type="boolean")
     * @Groups({"api"})
     * @SerializedName("registrationValidation")
     */
    protected $registrationValidation = false;

    /**
     * @ORM\ManyToOne(
     *     targetEntity="Claroline\CoreBundle\Entity\Model\WorkspaceModel"
     * )
     * @ORM\JoinColumn(name="workspace_model_id", nullable=true, onDelete="SET NULL")
     */
    protected $workspaceModel;

    /**
     * @ORM\Column(name="tutor_role_name", nullable=true)
     */
    protected $tutorRoleName;

     /**
     * @ORM\Column(name="learner_role_name", nullable=true)
     */
    protected $learnerRoleName;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Claroline\CursusBundle\Entity\CourseSession",
     *     mappedBy="course"
     * )
     * @Groups({"api"})
     */
    protected $sessions;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"api"})
     * @SerializedName("icon")
     */
    protected $icon;

    public function __construct()
    {
        $this->sessions = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code)
    {
        $this->code = $code;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getPublicRegistration()
    {
        return $this->publicRegistration;
    }

    public function setPublicRegistration($publicRegistration)
    {
        $this->publicRegistration = $publicRegistration;
    }

    public function getPublicUnregistration()
    {
        return $this->publicUnregistration;
    }

    public function setPublicUnregistration($publicUnregistration)
    {
        $this->publicUnregistration = $publicUnregistration;
    }

    public function getRegistrationValidation()
    {
        return $this->registrationValidation;
    }

    public function setRegistrationValidation($registrationValidation)
    {
        $this->registrationValidation = $registrationValidation;
    }

    public function getWorkspaceModel()
    {
        return $this->workspaceModel;
    }

    public function setWorkspaceModel(WorkspaceModel $workspaceModel)
    {
        $this->workspaceModel = $workspaceModel;
    }

    public function getTutorRoleName()
    {
        return $this->tutorRoleName;
    }

    public function setTutorRoleName($tutorRoleName)
    {
        $this->tutorRoleName = $tutorRoleName;
    }

    public function getLearnerRoleName()
    {
        return $this->learnerRoleName;
    }

    public function setLearnerRoleName($learnerRoleName)
    {
        $this->learnerRoleName = $learnerRoleName;
    }

    public function getSessions()
    {
        return $this->sessions->toArray();
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    public function __toString()
    {
        return $this->getTitle() . ' [' . $this->getCode() . ']';
    }
}