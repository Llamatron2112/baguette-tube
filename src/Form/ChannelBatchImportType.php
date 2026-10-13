<?php

namespace App\Form;

// use App\Entity\Channel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChannelBatchImportType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add("channels", TextareaType::class, [
                "label" => "Channels (un par ligne)",
                "attr" => [
                    "rows" => 15,
                    "class" => "w-100",
                    "placeholder" => "channel-1\nchannel-2",
                ],
                "constraints" => [new NotBlank()],
            ])
            ->add("submit", SubmitType::class, [
                "label" => "Importer",
                "attr" => ["class" => "btn btn-primary"],
            ]);
    }
}
