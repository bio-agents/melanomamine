<?php

namespace Melanomamine\FrontendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use \Elastica9205\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ContainerInterface;


class ChemspiderController extends Controller
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function searchChemspider($compoundName){
        $message="inside searchChemspider";
        ////CALL TO SERVICE FROM CONTROLLER
        //$chemSpider=$this->get('melanomamine.searchChemSpider');
        //$dato=$chemSpider->searchChemspider("Modafinil");

        $chemSpiderScriptPath=$this->container->get('kernel')->getRootDir(). "/../web/scripts/chemspider.py '$compoundName'";
        $command="python ".$chemSpiderScriptPath;
        $process = new Process($command);
        $process->run();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
        $output=$process->getOutput();
        $output_trimmed=rtrim($output);
        #echo $output;
        return $output_trimmed;
    }
}