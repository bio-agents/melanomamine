<?php

namespace Melanomamine\FrontendBundle\Twig\Extension;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Twig_Extension;
use Twig_Filter_Method;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChemspiderExtension extends \Twig_Extension
{
    protected $container;
    protected $searchChemSpider;

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->searchChemspider = $container->get('melanomamine.searchChemSpider');
    }

    public function getFilters()
    {
        return array(
            'retrieveChemSpiderId' => new \Twig_Filter_Method($this, 'retrieveChemSpiderId'),
        );
    }

    public function retrieveChemSpiderId($compoundName)
    {
        $message = "Inside retrieveChemSpiderId";
        //// CALL TO SERVICE FROM TEMPLATE
        /////  {{ compoundName | retrieveChemSpiderId  }}
        $chemSpiderScriptPath=$this->container->get('kernel')->getRootDir(). "/../web/scripts/chemspider.py '$compoundName'";
        $command="python ".$chemSpiderScriptPath;
        $process = new Process($command);
        $process->run();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
        $output=$process->getOutput();
        #echo $output;
        return $output;
    }

    public function getName()
    {
        return 'melanomamine_chemspider_extension';
    }

}


?>