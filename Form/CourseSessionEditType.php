<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CursusBundle\Form;

use Claroline\CursusBundle\Entity\CourseSession;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CourseSessionEditType extends AbstractType
{
    private $session;

    public function __construct(CourseSession $session)
    {
        $this->session = $session;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $workspace = $this->session->getWorkspace();

        $builder->add(
            'name',
            'text',
            array('required' => true)
        );
        $attr = array();
        $attr['class'] = 'datepicker input-small';
        $attr['data-date-format'] = 'dd-mm-yyyy';
        $attr['autocomplete'] = 'off';
        $builder->add(
            'start_date',
            'date',
            array(
                'required' => false,
                'format' => 'dd-MM-yyyy',
                'widget' => 'single_text',
                'attr' => $attr,
                'input' => 'datetime'
            )
        );
        $builder->add(
            'end_date',
            'date',
            array(
                'required' => false,
                'format' => 'dd-MM-yyyy',
                'widget' => 'single_text',
                'attr' => $attr,
                'input' => 'datetime'
            )
        );
        $builder->add(
            'sessionStatus',
            'choice',
            array(
                'required' => true,
                'choices' => array (
                    0 => 'session_not_started',
                    1 => 'session_open',
                    2 => 'session_closed'
                )
            )
        );

        if (!is_null($workspace)) {
            $builder->add(
                'learnerRole',
                'entity',
                array(
                    'required' => true,
                    'class' => 'ClarolineCoreBundle:Role',
                    'query_builder' => function (EntityRepository $er) use ($workspace) {

                        return $er->createQueryBuilder('r')
                            ->join('r.workspace', 'w')
                            ->where('w.id = :workspaceId')
                            ->setParameter('workspaceId', $workspace->getId())
                            ->orderBy('r.translationKey', 'ASC');
                    },
                    'property' => 'translationKey'
                )
            );
            $builder->add(
                'tutorRole',
                'entity',
                array(
                    'required' => true,
                    'class' => 'ClarolineCoreBundle:Role',
                    'query_builder' => function (EntityRepository $er) use ($workspace) {

                        return $er->createQueryBuilder('r')
                            ->join('r.workspace', 'w')
                            ->where('w.id = :workspaceId')
                            ->setParameter('workspaceId', $workspace->getId())
                            ->orderBy('r.translationKey', 'ASC');
                    },
                    'property' => 'translationKey'
                )
            );
        }
    }

    public function getName()
    {
        return 'course_session_form';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array('translation_domain' => 'cursus'));
    }
}
