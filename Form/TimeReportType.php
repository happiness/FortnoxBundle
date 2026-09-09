<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Form;

use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\CustomerType;
use App\Form\Type\ProjectType;
use KimaiPlugin\FortnoxBundle\Form\Model\TimeReportFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TimeReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', CustomerType::class, [
                'placeholder' => 'fortnox.form.choose_customer',
                'label' => 'fortnox.form.customer',
                'project_enabled' => true,
                'project_select' => 'project',
                'start_date_param' => '%begin%',
                'end_date_param' => '%end%',
            ])
            ->add('project', ProjectType::class, [
                'placeholder' => 'fortnox.form.choose_project',
                'label' => 'fortnox.form.project',
                'activity_enabled' => false,
            ])
            ->add('begin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'fortnox.form.begin',
            ])
            ->add('end', DateType::class, [
                'widget' => 'single_text',
                'label' => 'fortnox.form.end',
            ])
            ->add('billableOnly', CheckboxType::class, [
                'required' => false,
                'label' => 'fortnox.form.billable_only',
            ])
            ->add('notExportedOnly', CheckboxType::class, [
                'required' => false,
                'label' => 'fortnox.form.not_exported_only',
            ]);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event): void {
                /** @var TimeReportFilter|null $data */
                $data = $event->getData();
                $customer = $data?->customer;
                $project = $data?->project;

                $event->getForm()->add('project', ProjectType::class, [
                    'placeholder' => 'fortnox.form.choose_project',
                    'label' => 'fortnox.form.project',
                    'activity_enabled' => false,
                    'customers' => $customer,
                    'projects' => $project,
                ]);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                /** @var array<string, mixed> $data */
                $data = $event->getData();
                $customer = \array_key_exists('customer', $data) && $data['customer'] !== '' ? $data['customer'] : null;
                $project = \array_key_exists('project', $data) && $data['project'] !== '' ? $data['project'] : null;

                $event->getForm()->add('project', ProjectType::class, [
                    'placeholder' => 'fortnox.form.choose_project',
                    'label' => 'fortnox.form.project',
                    'activity_enabled' => false,
                    'customers' => $customer,
                    'projects' => $project,
                ]);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeReportFilter::class,
        ]);
    }
}
