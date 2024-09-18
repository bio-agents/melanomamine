<?php

namespace Melanomamine\FrontendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use \Elastica9205\Request;



class GeneSetEnrichmentController extends Controller
{

    public function cleanOldFiles($path){
        $message="inside cleanOldFiles";
        if (file_exists($path)) {
            foreach (new \DirectoryIterator($path) as $fileInfo) {
                if ($fileInfo->isDot()) {
                continue;
                }
                if ((time() - $fileInfo->getCTime() >= 345600)and(!$fileInfo->isDir())) {//4*24*60*60 = 4 dias
                    unlink($fileInfo->getRealPath());
                }
            }
        }
    }

    public function generateArrayTranslations($arrayTranslation){
        /*
            This function translate an array with lines like this: "673	ENSG00000157764"  into an associative array with keys=EnsembleGeneID and value=Entrez Gene ID. This array is returned and can be used later as a Ensemble <- -> Entrez translator
        */
        $arrayReturn=array();
        $arrayEnsembEntrez=array();
        $arrayEntrezEnsemb=array();
        //ld($arrayTranslation);
        for($x=2; $x<count($arrayTranslation); $x++){
            $line=$arrayTranslation[$x];
            $arrayLine=explode("\t", $line);
            $entrezGeneId=$arrayLine[0];
            if(count($arrayLine)!=1){
                $ensembleGeneId=$arrayLine[1];
            }else{
                $ensembleGeneId="";
            }
            $arrayTmp=array();
            $arrayTmp["Entrez"]=$entrezGeneId;
            $arrayTmp["Ensembl"]=$ensembleGeneId;

            //We have to load $arrayEntrezEnsembl and $arrayEnsemblEntrez
            //$arrayEntrezEnsembl has no duplicates, there is one key="EntrezGeneId" for one value="Ensembl Gene ID"
            $arrayEntrezEnsemb[$entrezGeneId]=$ensembleGeneId;
            //But $arrayEnsembl may have duplicates, one Ensembl Gene Id for one or more Entrez Gene ID, therefore we create a dictionary of arrays
            if (array_key_exists($ensembleGeneId, $arrayEnsembEntrez) and $ensembleGeneId!=""){ //161574
                //already exists, we retrieve its array and update with the new value
                $arrayTmp=$arrayEnsembEntrez[$ensembleGeneId];
                array_push($arrayTmp, $entrezGeneId);
                $arrayTmp=array_unique($arrayTmp);
                $arrayEnsembEntrez[$ensembleGeneId]=$arrayTmp;
            }elseif($ensembleGeneId != ""){
                //does not exist, we create a new entry for this ensemblGeneId
                $arrayTmp=array();
                array_push($arrayTmp, $entrezGeneId);
                $arrayEnsembEntrez[$ensembleGeneId]=$arrayTmp;
            }
        }
        $arrayReturn[0]=$arrayEntrezEnsemb;
        $arrayReturn[1]=$arrayEnsembEntrez;
        return $arrayReturn;
    }

    public function geneSetEnrichmentAction($whatToSearch, $entityName){
        $message="Inside geneSetEnrichmentAction workflow";
        $session = $this->getRequest()->getSession();
        $uniqueGenesList = $session->get('uniqueGenesList');
        $arrayEntrezMention = $session->get('arrayEntrezMention');
        $arrayGeneSetEnrichment=array();
        $arrayTranslation = array();

        //once we have the list of genes that we are working with, we have to save them in a file to use it later for the enrichment
        $path = $this->get('kernel')->getRootDir(). "/../web/files/enrichment";
        //Before anything, we clean all the old files inside the directory:
        $this->cleanOldFiles($path);

        $date=date("Y-m-d_H:i:s");
        $filename = $entityName."-".$date;
        $pathToFile="$path/$filename";
        $text="";
        foreach($uniqueGenesList as $key => $ncbiGeneId)
        {
            $text .=  $ncbiGeneId."\n";
        }
        if($text==""){$text=" ";}
        $fp = fopen($pathToFile, "w") or die("Could not open log file.");
        $results = fwrite($fp, $text) or die("Could not write file!"); //Now in results we have the output from fwrite
        //Once we have the file with the list of the EntrezGeneIDs, we call translate service to get translations for this ids to EnsemblGeneIDs, the type of output for the next step, therefore we have translations for EnsembleGeneIDs to EntrezGeneIDs.

        $pathTranslations = $this->get('kernel')->getRootDir(). "/../web/files/enrichment/translations";
        $this->cleanOldFiles($pathTranslations);//We clean also old files from translations directory
        $pathTranslationsFile="$pathTranslations/$filename";
        $cmd= "curl -H 'Expect:' -L http://rbbt.bioinfo.cnio.es/Translation/tsv_translate -F '_format=raw' -F 'genes=@$pathToFile' -F 'format=Ensembl Gene ID'";
        exec($cmd,$arrayTranslation);
        $arrayTranslation=$this->generateArrayTranslations($arrayTranslation);
        $arrayEntrezEnsemb=$arrayTranslation[0];
        $arrayEnsembEntrez=$arrayTranslation[1];

        //In $pathToFile we have the list of genes to perform Gene Set Enrichment Analysis using curl
        //curl -H "Expect:" -L http://rbbt.bioinfo.cnio.es/Enrichment/enrichment -F "_format=raw" -F "list=@/home/mvazquezg/test/genes" -F "database=go_bp"

        $databasesToPerformGSE=array("kegg","go_bp","pfam","reactome","matador","corum"); // ,"interpro" is not working
        foreach($databasesToPerformGSE as $database){
            $cmd="curl -H 'Expect:' -L http://rbbt.bioinfo.cnio.es/Enrichment/enrichment -F '_format=raw' -F 'list=@$pathToFile' -F 'database=$database'";
            $arrayResults=array();
            exec($cmd,$arrayResults);
            if (count($arrayResults) != 0){
                $arrayGeneSetEnrichment[$database] = $arrayResults;
            }
        }

        /*We have the results as an array at $arrayGeneSetEnrichment[kegg], $arrayGeneSetEnrichment[go_bp], $arrayGeneSetEnrichment[go_mf], $arrayGeneSetEnrichment[pfam]...

            $arrayLines=$arrayGeneSetEnrichment["kegg"]

            and then...

            arrayLines[0]= #: :namespace=Hsa/feb2014#:type=:double
            arrayLines[1]= #GO ID	p-value	Ensembl Gene ID
            arrayLines[2]= GO:0043066	0.0005251241665266586	ENSG00000157764|ENSG00000187098|ENSG00000168036|ENSG00000146247
            arrayLines[3]= GO:0007165	0.013391764755693947	ENSG00000157764|ENSG00000120217|ENSG00000213281|ENSG00000135446
            arrayLines[4]= GO:0045892	0.004456249536718431	ENSG00000178764|ENSG00000168036|ENSG00000147889
            arrayLines[5]= GO:0006357	0.0016404425455952194	ENSG00000178764|ENSG00000184486|ENSG00000168036
            ... and so on...
        */
        fclose($fp);
        //ldd($arrayGeneSetEnrichment);
        return $this->render('MelanomamineFrontendBundle:Search:geneSetEnrichment.html.twig', array(
            'entityName' => $entityName,
            'entityType' => "genes",
            'whatToSearch' => $whatToSearch,
            'arrayEntrezEnsembl' => $arrayEntrezEnsemb,
            'arrayEnsembEntrez' => $arrayEnsembEntrez,
            'arrayGeneSetEnrichment' => $arrayGeneSetEnrichment,
            'arrayEntrezMention' => $arrayEntrezMention,

        ));
    }
}
