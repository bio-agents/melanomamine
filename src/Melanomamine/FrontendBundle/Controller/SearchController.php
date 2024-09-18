<?php

namespace Melanomamine\FrontendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use \Elastica9205\Request;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;



class SearchController extends Controller
{
    public function escapeElasticReservedChars($string) {
        $regex = "/[\\+\\-\\=\\&\\|\\!\\(\\)\\{\\}\\[\\]\\^\\\"\\~\\*\\<\\>\\?\\:\\\\\\/]/";
        return preg_replace($regex, addslashes('\\$0'), $string);
    }

    public function mmmr($array, $output = 'mean'){
        //Function to get mean(default), median, mode and range of $array input
        if(!is_array($array)){
            return FALSE;
        }else{
            switch($output){
                case 'mean':
                    $count = count($array);
                    $sum = array_sum($array);
                    $total = $sum / $count;
                break;
                case 'median':
                    rsort($array);
                    $middle = round(count($array) / 2);
                    $total = $array[$middle-1];
                break;
                case 'range':
                    sort($array);
                    $sml = $array[0];
                    rsort($array);
                    $lrg = $array[0];
                    $total = $lrg - $sml;
                break;
            }
            return $total;
        }
    }

    public function getMmmrScore($resultSetDocuments, $orderBy, $operation = 'mean'){
        //Function that receives a resultSetDocuments and extracts the mean value of the score

        $arrayResults=$resultSetDocuments->getResults();
        if(count($arrayResults)==0){
            return 0;
        }
        $arrayInput=array();
        foreach($arrayResults as $result){
            if($orderBy=="score"){
                $orderBy="score";
            }
            $arrayData=$result->getSource();
            $data=$arrayData[$orderBy];
            if($data==null){
                $data=0;
            }
            $arrayInput[]=$data;
        }
        if(count($arrayInput)!=0){
            $output=$this->mmmr($arrayInput, $operation);
        }
        else{
            $output=0;
        }
        return $output;
    }

    public function insertMention($dictionarySummary,$field, $mention){
        //First of all we search for the $field key inside dictionary
        if (array_key_exists($field, $dictionarySummary)){
            //We search for the correct place.
            //First we search if the mention exists
            $dictionaryField=$dictionarySummary[$field];
            if(array_key_exists($mention, $dictionaryField)){
                //If the mention already exists, we count this new mention.
                $counter=$dictionaryField[$mention];
                $dictionaryField[$mention]=$counter+1;
                $dictionarySummary[$field]=$dictionaryField;
            }else{
                //We create a new entry for this mention
                $dictionaryField[$mention]=1;
                $dictionarySummary[$field]=$dictionaryField;
            }
        }else{
            //Generate a new entry for it in the specified field
            $tmpDictionary=[];
            $tmpDictionary[$mention]=1;
            $dictionarySummary[$field]=$tmpDictionary;
        }

        return $dictionarySummary;
    }

    public function insertIdMentions($dictionarySummary,$field, $geneId, $mention){
        //First of all we search for the $field key inside dictionary
        if (array_key_exists($field, $dictionarySummary)){
            //First we search if the geneId exists
            $geneIdDictionary=$dictionarySummary[$field];
            if(array_key_exists($geneId, $geneIdDictionary)){
                //We search for the mention
                $mentionDictionary=$geneIdDictionary[$geneId];
                if(array_key_exists($mention, $mentionDictionary)){
                    //We update the counter for this mention for this geneId
                    $counter=$mentionDictionary[$mention];
                    $mentionDictionary[$mention]=$counter+1;
                    $geneIdDictionary[$geneId]=$mentionDictionary;
                    $dictionarySummary[$field]=$geneIdDictionary;
                }else{
                    //We create the entry for the mention
                    $mentionDictionary[$mention]=1;
                    $geneIdDictionary[$geneId]=$mentionDictionary;
                    $dictionarySummary[$field]=$geneIdDictionary;
                }
            }else{
                //We create the entry for the geneId
                $tmpDictionary=[];
                $tmpDictionary[$mention]=1;
                $geneIdDictionary[$geneId]=$tmpDictionary;
                $dictionarySummary[$field]=$geneIdDictionary;
            }
        }else{
            //Generate a new entry for it in the specified field
            $tmpDictionary=[];
            $tmpDictionary[$mention]=1;
            $tmpGeneIdDictionary=[];
            $tmpGeneIdDictionary[$geneId]=$tmpDictionary;
            $dictionarySummary[$field]=$tmpGeneIdDictionary;
        }

        return $dictionarySummary;
    }

    public function countMentionsInArray($arrayMentions){
        $arrayValues=array_values($arrayMentions);
        $sum = array_sum($arrayValues);
        return($sum);
    }

    public function extractGenesAndCompounds($arrayAbstracts){
        $message="Inside of extractGenesAndCompounds";
        $arrayGenes=array();
        $arrayCompounds=array();
        $arrayGenesAdded=array();
        $arrayCompoundsAdded=array();
        foreach($arrayAbstracts as $abstract){
            if(array_key_exists("genes3", $abstract->getSource())){
                $arrayGenesTmp=$abstract->getSource()["genes3"];
                if (count($arrayGenesTmp)!=0){
                    foreach($arrayGenesTmp as $gene){
                        if(!in_array($gene["ontology"], $arrayGenesAdded)){
                            array_push($arrayGenes, $gene);
                            array_push($arrayGenesAdded,$gene["ontology"]);
                        }
                    }
                }
            }
            if(array_key_exists("chemicals2", $abstract->getSource())){
                $arrayCompoundsTmp=$abstract->getSource()["chemicals2"];
                if (count($arrayCompoundsTmp)!=0){
                    foreach($arrayCompoundsTmp as $compound){
                        if(!in_array($compound["mention"], $arrayCompoundsAdded)){
                            array_push($arrayCompounds, $compound);
                            array_push($arrayCompoundsAdded,$compound["mention"]);
                        }
                    }
                }
            }
        }
        $arrayReturn=array();
        $arrayReturn["arrayGenes"]=$arrayGenes;
        $arrayReturn["arrayCompounds"]=$arrayCompounds;
        return $arrayReturn;
    }
    public function getArrayIntersection($array1,$array2,$type){
        $arrayIntersection=array();
        $arrayPmids2=array();
        if($type=="pmids"){
            //We will insert elements inside arrayIntersection if the pmids are equal. So we create an array with the pubmedIds so we don't have to loop over the array2 everytime.
            foreach($array2 as $element=>$value){
                $arrayTmp=array_keys($value);
                $arrayPmids2=array_merge($arrayPmids2,$arrayTmp);
            }
            $arrayPmids2 = array_unique($arrayPmids2);

            //once we have an array with the pmids from the array2, we can iterate over array1 and get intersection (saving the whole element with pmid, title, text and score)
            foreach($array1 as $element=>$value){
                foreach($value as $pmid=>$valuesAbstract){
                    if (in_array($pmid, $arrayPmids2)){
                        $arrayIntersection[$pmid]=$valuesAbstract;
                    }
                }
            }
            usort($arrayIntersection, function($a, $b)
            {
                $score_a=$a["score"];
                $score_b=$b["score"];
                if($score_a==$score_b){
                    return 0;
                }return ($score_a > $score_b) ? -1 : 1;
            });
        }
        else{
            //We also generate $arrayPmids2
            $arrayIds2=array();
            foreach($array2 as $value){
                if($type=="genes"){
                    $id=$value["ontology"];
                }else{//$type=="compounds"
                    $id=$value["mention"];
                }
                array_push($arrayIds2, $id);
            }
            $arrayIds2 = array_unique($arrayIds2);

            foreach($array1 as $element1){
                if($type=="genes"){
                    $id=$element1["ontology"];
                }elseif($type=="compounds"){//=="compounds"
                    $id=$element1["mention"];
                    //We should have to normalize using compounddict.
                }
                if(in_array($id, $arrayIds2)){
                    array_push($arrayIntersection, $element1);
                }
            }
        }
        return($arrayIntersection);
    }

    public function getRidOfDuplicated($arrayTerms,$type){
        $arrayUnique=array();
        $inserted=array();
        foreach($arrayTerms as $term){
            if($type=="genes"){
                $ncbiGeneId=$term["ontology"];
                if(!in_array($ncbiGeneId, $inserted)){
                    array_push($arrayUnique, $term);
                    array_push($inserted, $ncbiGeneId);
                }
            }else{//$type==compounds
                $mention=$term["mention"];
                if(!in_array($mention, $inserted)){
                    array_push($arrayUnique, $term);
                    array_push($inserted, $mention);
                }
            }
        }
        return $arrayUnique;
    }

    public function generateSummary($concept1, $id, $type){
        $message="generateSummary";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
        $elasticaQuery2 = new \Elastica\Query();
        $elasticaQuery2->setSize(500);
        $elasticaQuery2->setSort(array('melanoma_score_2' => array('order' => 'desc')));
        $queryBool2 = new \Elastica\Query\BoolQuery();
        $searchNested2 = new \Elastica\Query\QueryString();
        $searchNested2->setParam('query', $id);
        if($type=="disease"){
            $searchNested2->setParam('fields', array('diseases3.ontologyId'));
        }elseif($type=="gene"){
            $searchNested2->setParam('fields', array('genes3.ontology'));
        }
        $nestedQuery2 = new \Elastica\Query\Nested();
        $nestedQuery2->setQuery($searchNested2);
        if($type=="disease"){
            $nestedQuery2->setPath('diseases3');
        }elseif($type=="gene"){
            $nestedQuery2->setPath('genes3');
        }
        $queryBool2->addMust($nestedQuery2);
        $elasticaQuery2->setQuery($queryBool2);
        $data2 = $finder->search($elasticaQuery2);
        $arrayResults2=$data2->getResults();
        $summary=$this->createSummaryTable($arrayResults2, $concept1, "knowledge");
        return $summary;
    }

    public function performDictionarySearch($searchTerm, $fieldToSearch, $type){
        $message="inside performBasicSearch";
        //ld($type);
        //ld($searchTerm);
        //ld($fieldToSearch);
        $finder = $this->container->get('fos_elastica.index.melanomamine.'.$type);
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(1);
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $searchTerm = $this->escapeElasticReservedChars($searchTerm);
        $queryString->setParam('query', $searchTerm);
        $queryString->setParam('fields', array($fieldToSearch));
        //ld($queryString);
        $queryBool->addMust($queryString);
        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);

        //Now we have to iterate over the values of these results and repeat the search

        return($data);
    }

    public function performBasicSearch($searchTerm, $fieldToSearch, $type){
        $message="inside performBasicSearch";
        //ld($type);
        //ld($searchTerm);
        //ld($fieldToSearch);
        $finder = $this->container->get('fos_elastica.index.melanomamine.'.$type);
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(2000);
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $searchTerm = $this->escapeElasticReservedChars($searchTerm);
        $queryString->setParam('query', $searchTerm);
        $queryString->setParam('fields', array($fieldToSearch));
        //ld($queryString);
        $queryBool->addMust($queryString);
        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);

        //Now we have to iterate over the values of these results and repeat the search

        return($data);
    }

    public function performBasicSearchMultipleFields($searchTerm, $arrayFields, $type){
        $message="inside performBasicSearch";
        //ld($type);
        //ld($searchTerm);
        //ldd($arrayFields);
        $finder = $this->container->get('fos_elastica.index.melanomamine.'.$type);
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));
        $elasticaQuery->setSize(5);
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();


        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $searchTerm = $this->escapeElasticReservedChars($searchTerm);
        $queryString->setParam('query', "$searchTerm");
        $queryString->setParam('fields', array($arrayFields));
        //ld($queryString);
        $queryBool->addMust($queryString);

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        ldd($message);
        //Now we have to iterate over the values of these results and repeat the search

        return($data);
    }

    public function performNestedSearch($entityName, $type, $mapping, $property){
        $message="inside performNestedSearch";
        $finder = $this->container->get('fos_elastica.index.melanomamine.'.$type);
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $entityName=$this->escapeElasticReservedChars($entityName);
        $searchNested->setParam('query', $entityName);
        $searchNested->setParam('fields', array($mapping.'.'.$property));

        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        $nestedQuery->setPath($mapping);

        $queryBool->addMust($nestedQuery);

        $elasticaQuery->setQuery($queryBool);


        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();
        return $arrayAbstracts;

    }

    public function performUnlimitedNestedSearch($entityName, $type, $mapping, $property){
        $message="inside performNestedSearch";
        $max_size=200;
        $finder = $this->container->get('fos_elastica.index.melanomamine.'.$type);
        $elasticaQuery = new \Elastica\Query();
        //$elasticaQuery->setSize(2000);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $entityName=$this->escapeElasticReservedChars($entityName);
        $searchNested->setParam('query', $entityName);
        $searchNested->setParam('fields', array($mapping.'.'.$property));

        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        $nestedQuery->setPath($mapping);

        $queryBool->addMust($nestedQuery);

        $elasticaQuery->setQuery($queryBool);


        $data = $finder->search($elasticaQuery);
        $totalHits=$data->getTotalHits();
        if($totalHits<$max_size){
            $max_size=$totalHits; //!!!!!!!!!!!!!!!!So we can perform searches that never returns more tha totalHits!!!!!!!!!!!!!
        }
        $elasticaQuery->setSize($max_size);
        $data = $finder->search($elasticaQuery);
        return $data;

    }
    public function isAmbiguousTerm($concept, $entityType){
        //This function returns an id to search with or a resultSet with the multiple results to go to disambiguation process, or a value of 0 if that term is not a valid entityType/concept to search with
        $message="Inside isAmbiguousTerm";
        //ld($concept);
        //ld($entityType);

        switch ($entityType){
            case "disease":

                $data=$this->performBasicSearch($concept, "ontologyId", "diseasesDictionary");
                $totalHits=$data->getTotalHits();
                if($totalHits!=0){
                    //it's a meshId/OMIMid. We get first and continue. No disambiguation Process needed
                    $meshId=$data->getResults()[0]->getSource()["ontologyId"];
                    return $meshId;

                }else{
                    //Either it's a disease or a meshId not found inside diseasesDictionary. We search inside disease field of diseasesDictionary

                    $data=$this->performBasicSearch($concept, "disease", "diseasesDictionary");
                    $results=$data->getResults();
                    $totalHits=$data->getTotalHits();
                    if($totalHits==1){
                        //We got the right disease, we just need to pickup the meshId
                        $meshId=$results[0]->getSource()["ontologyId"];
                        return $meshId;
                    }elseif($totalHits>1){
                        //We need disambiguation process since there are several diseases with this name inside diseaseDictionary with different meshId, We need to filter and figure out which one is the correct.
                        $message="Going to disambiguation process of \"$concept\" term";
                        return $data;
                    }else{
                        //$totalHits == 0. Disease not found!!!
                        return "0";
                    }
                }
                break;
            case "gene":

                $totalHits=0;
                if(is_numeric($concept)){
                    //Then we can perform basis search against ncbiGeneId
                    $data=$this->performBasicSearch($concept, "ncbiGeneId", "genesDictionary");
                    $totalHits=$data->getTotalHits();
                    ldd($totalHits);
                }
                if($totalHits!=0){
                    //it's a ncbiGeneId. We get first and continue. No disambiguation Process needed
                    $ncbiGeneId=$data->getResults()[0]->getSource()["ncbiGeneId"];
                    return $ncbiGeneId;

                }else{
                    //Either it's a gene or a ncbiGeneId not found inside genesDictionary. We search inside geneProteinName field of genesDictionary
                    $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
                    $elasticaQuery = new \Elastica\Query();
                    $elasticaQuery->setSize(1000000);
                    $queryBool = new \Elastica\Query\BoolQuery();
                    $queryString = new \Elastica\Query\QueryString();
                    $queryString->setParam('query', $concept);
                    if(is_numeric($concept)){
                        $queryString->setParam('fields', array("geneProteinName","ncbiGeneId"));
                    }else{//Cannot be an ncbiGeneId
                        $queryString->setParam('fields', array("geneProteinName"));
                    }
                    $queryBool->addMust($queryString);
                    $elasticaQuery->setQuery($queryBool);
                    $data = $finder->search($elasticaQuery);
                    $results=$data->getResults();
                    $totalHits=$data->getTotalHits();
                    if($totalHits==1){
                        //We got the right disease, we just need to pickup the meshId
                        $meshId=$results->getSource()["ontologyId"];
                        return $meshId;
                    }elseif($totalHits>1){
                        //We need disambiguation process since there are several diseases with this name inside diseaseDictionary with different meshId, We need to filter and figure out which one is the correct.
                        $message="Going to disambiguation process of \"$concept\" term";
                        return $data;
                    }else{
                        //$totalHits == 0. Disease not found!!!
                        return "0";
                    }
                }
                break;
            case "freeText":

                $arrayFields=["title","text"];
                $data=$this->performBasicSearch($concept, "text", "abstracts");
                $totalHits=$data->getTotalHits();
                if($totalHits!=0){
                    //No disambiguation Process needed
                    return $data;
                }else{
                    return "0";
                }
                break;
        }
    }



    public function getDirectAssociations ($concept, $entityType){

        //Once here there is no need for disambiguation. $concept contains MeshId or OMIMid to search for
        //So we perform the search for this concept, depending on the entityType but taking into account that we have MeshId/OMIMid inside
        $type="abstracts";
        $mapping=$entityType."s";
        if($mapping=="diseases"){
            $mapping="diseases3";
            $property="ontologyId";
        }elseif($mapping=="genes"){
            $mapping="genes3";
            $property="ontology";
        }
        $data=$this->performUnlimitedNestedSearch($concept, $type, $mapping, $property);
        $arrayResults=$data->getResults();
        $arrayAbstracts=array();
        foreach($arrayResults as $result){
            $pmid=$result->getId();
            $source=$result->getSource();
            $arrayAbstracts[$pmid]=$source;
        }
        return($arrayAbstracts);
    }

    /*
    public function getArrayDiseasesGenesFreetextToSelectForIntersection($concept1){
        //Similar to next function but retrieves all posible arrays (diseases, genes, freeText) for a given concept, and later can be chosen to adjust intersection to whatever desired.
        $message="getArrayDiseasesGenesFreetextToSelectForIntersection";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(1);

        $queryBool = new \Elastica\Query\BoolQuery();
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $concept1);
        $queryString->setParam('fields', array("diseases3.mention","diseases3.ontologyId"));
        $queryBool->addMust($queryString);
        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);
        $arrayResults=$data->getResults();
        if(count($arrayResults)==1){//There is at least mention of this concept as diseases
            $meshidSet=false;
            $arrayDiseases=$arrayResults[0]->getSource()["diseases3"];
            $meshId="";
            foreach($arrayDiseases as $disease){
                $mention=$disease["mention"];
                $ontologyId=$disease["ontologyId"];
                if(($mention==$concept1) or ($ontologyId==$concept1)){
                    $meshId=$disease["ontologyId"];
                    break;
                }
            }
            if($meshId==""){
                $error="something went wrong searching meshId for $concept1";
                ldd($error);
            }
            //Once we have the meshId of the disease we are searching for, we want to have some kind of summary
            $summaryForDisease=$this->generateSummary($concept1, $meshId, "disease");
            $arrayReturn["diseases"]=$meshId;
            $arrayReturn["summaryDiseases"]=$summaryForDisease;
        }

        //Then we search if there is a gene for this concept1
        $ncbiGeneId="";
        $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(1);
        $queryBool = new \Elastica\Query\BoolQuery();
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $concept1);
        if(is_numeric($concept1)){
            $queryString->setParam('fields', array("geneProteinName","ncbiGeneId"));
        }else{//Cannot be an ncbiGeneId
            $queryString->setParam('fields', array("geneProteinName"));
        }
        $queryBool->addMust($queryString);
        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);
        $arrayResults=$data->getResults();
        if(count($arrayResults)==1){
            $ncbiGeneId=$arrayResults[0]->getSource()["ncbiGeneId"];
            $summaryForGene=$this->generateSummary($concept1, $ncbiGeneId, "gene");
            $arrayReturn["genes"]=$ncbiGeneId;
            $arrayReturn["summaryGenes"]=$summaryForGene;
        }

        //Now we perform a Free-text search on the text and title
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
        $elasticaQuery  = new \Elastica\Query();
        $elasticaQuery->setSize(500);

        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        $queryString  = new \Elastica\Query\QueryString();
        //'And' or 'Or' default : 'Or'
        $queryString->setDefaultOperator('AND');
        $queryString->setQuery($concept1);
        $elasticaQuery->setQuery($queryString);
        $data = $finder->search($elasticaQuery);
        $arrayResults=$data->getResults();
        $summaryForFreeText=$this->createSummaryTable($arrayResults, $concept1, "knowledge");

        $arrayReturn["freetext"]=$concept1;
        $arrayReturn["summaryFreeText"]=$summaryForFreeText;

        return($arrayReturn);

    }
    */

    public function insertElementDictionary($arrayGenes, $ncbiGeneId, $pmid){
        if(array_key_exists($ncbiGeneId, $arrayGenes)){
            //We update arrayGenes
            $arrayPmidsCounter=$arrayGenes[$ncbiGeneId];
            if(array_key_exists($pmid, $arrayPmidsCounter)){
                //We update arrayPmidsCounter
                $counter=$arrayPmidsCounter[$pmid];
                $arrayPmidsCounter[$pmid]=$counter+1;
                $arrayGenes[$ncbiGeneId]=$arrayPmidsCounter;
            }else{
                //We create a new entry for this pmid
                $arrayPmidsCounter[$pmid]=1;
                $arrayGenes[$ncbiGeneId]=$arrayPmidsCounter;
            }
        }else{
            //We create a new entry for $arrayGenes
            $arrayTmp=array();
            $arrayTmp[$pmid]=1;
            $arrayGenes[$ncbiGeneId]=$arrayTmp;
        }
        return $arrayGenes;
    }

    public function getIndirectAssociations($arrayPmids1, $arrayAbstracts1, $arrayPmids2, $arrayAbstracts2){
        //Function to extract indirect associations between term1 and term2
        //We have to repeat the same for genes and compounds
        //We first generate an array of genes for each term.($arrayGenesTerm1 and $arrayGenesTerm2) as well an array of compounds for each term ($arrayCompoundsTerm1, $arrayCompoundsTerm2). Every gene and every compound have to be linked to its pmid that they belong to, so we can build up the next arrays (intersection with same pmid in both lists, intersection with different pmid in both lists, lists separated with the rest of elements not intersected in any way)
        $message="getIndirectAssociations";
        $arrayGenes1=array();
        $arrayGenes2=array();
        $arrayCompounds1=array();
        $arrayCompounds2=array();
        foreach($arrayAbstracts1 as $pmid=>$value){
            if(array_key_exists("chemicals2", $value)){
                $arrayOfCompounds=$value["chemicals2"];
                foreach($arrayOfCompounds as $compound){
                    $mention=strtolower($compound["mention"]);
                    //ld($mention);//We need to normalize compounds using the dictionary. But we cannot do it because we have no field to do it. Instead of it we create a strtolower keys dictionary ... :-(
                    $arrayCompounds1=$this->insertElementDictionary($arrayCompounds1, $mention, $pmid);
                }
            }
            if(array_key_exists("genes3", $value)){
                $arrayOfGenes=$value["genes3"];
                foreach($arrayOfGenes as $gene){
                    $ncbiGeneId=$gene["ontology"];
                    //$ncbiTaxId=$gene["ontologyId"]; No lo usamos ahora
                    $arrayGenes1=$this->insertElementDictionary($arrayGenes1, $ncbiGeneId, $pmid);
                }
            }
        }
        ksort($arrayCompounds1);
        ksort($arrayGenes1);
        foreach($arrayAbstracts2 as $pmid=>$value){
            if(array_key_exists("chemicals2", $value)){
                $arrayOfCompounds=$value["chemicals2"];
                foreach($arrayOfCompounds as $compound){
                    $mention=strtolower($compound["mention"]);
                    //ld($mention);//We need to normalize compounds using the dictionary. But we cannot do it because we have no field to do it. Instead of it we create a strtolower keys dictionary ... :-(
                    $arrayCompounds2=$this->insertElementDictionary($arrayCompounds2, $mention, $pmid);
                }
            }
            if(array_key_exists("genes3", $value)){
                $arrayOfGenes=$value["genes3"];
                foreach($arrayOfGenes as $gene){
                    $ncbiGeneId=$gene["ontology"];
                    //$ncbiTaxId=$gene["ontologyId"]; No lo usamos ahora
                    $arrayGenes2=$this->insertElementDictionary($arrayGenes2, $ncbiGeneId, $pmid);
                }
            }
        }
        ksort($arrayCompounds2);
        ksort($arrayGenes2);

        //Here we have all the data that we need. $arrayPmids1, $arrayPmids2, $arrayAbstracts1, $arrayAbstracts2, $arrayGenes1, $arrayGenes2, $arrayCompounds1, $arrayCompounds2
        //First we iterate over arrayPmids and we move the info to the good array where it belongs to.
        $arrayIntersectionGenesSamePmids=array();
        $arrayIntersectionGenesDifferentPmids=array();
        $arrayIntersectionCompoundsSamePmids=array();
        $arrayIntersectionCompoundsDifferentPmids=array();

        $arrayGenesSinglets1=array(); //array to save the geneIds of the genes that are only present in term1 but not term2 group of genes
        $arrayGenesSinglets2=array(); //array to save the geneIds of the genes that are only present in term2 but not term1 group of genes
        $arrayCompoundsSinglets1=array(); //array to save the mentions of the compounds that are only present in term1 but not term2 group of compounds
        $arrayCompoundsSinglets2=array(); //array to save the mentions of the compounds that are only present in term2 but not term1 group of compopunds

        //We have to iterate over keys(arrayGenes1) and keys($arrayGenes2);
        $arrayGeneIds1=array_keys($arrayGenes1);
        $arrayGeneIds2=array_keys($arrayGenes2);
        $arrayCompoundsMentions1=array_keys($arrayCompounds1);
        $arrayCompoundsMentions2=array_keys($arrayCompounds2);

        //We loop for genes indirect relations
        foreach($arrayGeneIds1 as $foo=>$geneId){

            if(in_array($geneId, $arrayGeneIds2)){
                //We need to know if they have the same pmid or not, in order to insert it in the correct $arrayIntersection
                $arrayPmidsCounter1=$arrayGenes1[$geneId];
                $arrayPmidsCounter2=$arrayGenes2[$geneId];
                if(count(array_intersect_key($arrayPmidsCounter1, $arrayPmidsCounter2))==0){
                    //they don't share the same pmid, going to $arrayIntersectionGenesDifferentPmids
                    array_push($arrayIntersectionGenesDifferentPmids, $geneId);
                }else{
                    //This geneIds share the same pmid, going to $arrayIntersectionGenesSamePmids
                    array_push($arrayIntersectionGenesSamePmids, $geneId);
                }
            }else{
                //Insert it to the array where geneIds don't intersect
                array_push($arrayGenesSinglets1, $geneId);
            }
        }

        //We don't forget to create $arrayGenesSinglets2, not included in previous loop (just implement this case because intersection is checked in previous loop)
        foreach($arrayGeneIds2 as $foo=>$geneId){
            if(!in_array($geneId, $arrayGeneIds1)){
                //Insert it to the array where geneIds don't intersect
                array_push($arrayGenesSinglets2, $geneId);
            }
        }

        //We loop for compounds indirect relations
        foreach($arrayCompoundsMentions1 as $foo=>$mention){

            if(in_array($mention, $arrayCompoundsMentions2)){
                //We need to know if they have the same pmid or not, in order to insert it in the correct $arrayIntersection
                $arrayPmidsCounter1=$arrayCompounds1[$mention];
                $arrayPmidsCounter2=$arrayCompounds2[$mention];
                if(count(array_intersect_key($arrayPmidsCounter1, $arrayPmidsCounter2))==0){
                    //they don't share the same pmid, going to $arrayIntersectionGenesDifferentPmids
                    array_push($arrayIntersectionCompoundsDifferentPmids, $mention);
                }else{
                    //This geneIds share the same pmid, going to $arrayIntersectionGenesSamePmids
                    array_push($arrayIntersectionCompoundsSamePmids, $mention);
                }
            }else{
                //Insert it to the array where geneIds don't intersect
                array_push($arrayCompoundsSinglets1, $mention);
            }
        }

        //We don't forget to create $arrayCompoundsSinglets2, not included in previous loop (just implement this case because intersection is checked in previous loop)
        foreach($arrayCompoundsMentions2 as $foo=>$mention){
            if(!in_array($mention, $arrayCompoundsMentions1)){
                //Insert it to the array where mentions don't intersect
                array_push($arrayCompoundsSinglets2, $mention);
            }
        }
        //ld($arrayIntersectionGenesSamePmids);
        //ld($arrayIntersectionGenesDifferentPmids);
        //ld($arrayGenesSinglets1);
        //ld($arrayGenesSinglets2);
        //ld($arrayIntersectionCompoundsSamePmids);
        //ld($arrayIntersectionCompoundsDifferentPmids);
        //ld($arrayCompoundsSinglets1);
        //ldd($arrayCompoundsSinglets2);

        $arrayReturn=array();
        $arrayReturn["arrayGenes1"]=$arrayGenes1;
        $arrayReturn["arrayGenes2"]=$arrayGenes2;
        $arrayReturn["arrayCompounds1"]=$arrayCompounds1;
        $arrayReturn["arrayCompounds2"]=$arrayCompounds2;
        $arrayReturn["arrayIntersectionGenesSamePmids"]=$arrayIntersectionGenesSamePmids;
        $arrayReturn["arrayIntersectionGenesDifferentPmids"]=$arrayIntersectionGenesDifferentPmids;
        $arrayReturn["arrayGenesSinglets1"]=$arrayGenesSinglets1;
        $arrayReturn["arrayGenesSinglets2"]=$arrayGenesSinglets2;
        $arrayReturn["arrayIntersectionCompoundsSamePmids"]=$arrayIntersectionCompoundsSamePmids;
        $arrayReturn["arrayIntersectionCompoundsDifferentPmids"]=$arrayIntersectionCompoundsDifferentPmids;
        $arrayReturn["arrayCompoundsSinglets1"]=$arrayCompoundsSinglets1;
        $arrayReturn["arrayCompoundsSinglets2"]=$arrayCompoundsSinglets2;

        return $arrayReturn;
    }

    public function searchDiseasesGenesFreeTextInOrder($concept1){
        //This function search for a concept to see if it is a disease (searching all info about disease), if don't it searches for genes, and last searches for free-text. Retrieves an array, of Result objects, with all the info related (and the type of the result?)
        $message="inside searchDiseasesGenesFreeTextInOrder";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(1);

        $queryBool = new \Elastica\Query\BoolQuery();
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $concept1);
        $queryString->setParam('fields', array("diseases3.mention"));
        $queryBool->addMust($queryString);
        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);
        $arrayResults=$data->getResults();
        //ld($arrayResults);
        if(count($arrayResults)==1){//It's a disease mention, we get meshId
            $meshidSet=false;
            $arrayDiseases=$arrayResults[0]->getSource()["diseases3"];
            $meshId="";
            foreach($arrayDiseases as $disease){
                $mention=$disease["mention"];
                if($mention==$concept1){
                    $meshId=$disease["ontologyId"];
                    break;
                }
            }
            if($meshId==""){
                $error="something went wrong searching meshId for $concept1";
                ldd($error);
            }
            //Once here we know concept1=Disease and we have its meshId to search all abstracts with diseases that have that meshId (implying that a Query expansion will be done)
            $elasticaQuery2 = new \Elastica\Query();
            $elasticaQuery2->setSize(500);
            $elasticaQuery2->setSort(array('melanoma_score_2' => array('order' => 'desc')));
            $queryBool2 = new \Elastica\Query\BoolQuery();
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('query', $meshId);
            $searchNested2->setParam('fields', array('diseases3.ontologyId'));

            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('diseases3');

            $queryBool2->addMust($nestedQuery2);
            $elasticaQuery2->setQuery($queryBool2);
            $data2 = $finder->search($elasticaQuery2);
            $arrayResults2=$data2->getResults();
            $arrayReturn=array();
            $arrayReturn[0]="diseases";
            $arrayReturn[1]=$arrayResults2;
            //We also return all the abstracts where this disease is co-mentioned:
            return($arrayReturn);

        }
        else{//It could be a meshId or a gene or none
            $message="inside else searchDiseasesGenesFreeTextInOrder. Searching for meshId or genes";
            //First we check if it is a meshId:
            $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
            $elasticaQuery = new \Elastica\Query();
            $elasticaQuery->setSize(1);

            $queryBool = new \Elastica\Query\BoolQuery();
            $queryString = new \Elastica\Query\QueryString();
            $queryString->setParam('query', $concept1);
            $queryString->setParam('fields', array("diseases3.ontologyId"));
            $queryBool->addMust($queryString);
            $elasticaQuery->setQuery($queryBool);
            $data = $finder->search($elasticaQuery);
            $arrayResults=$data->getResults();
            if (count($arrayResults)==1){
                //We have a meshId to search with, therefore implementing a query expansion.
                $arrayDiseases=$arrayResults[0]->getSource()["diseases3"];
                foreach($arrayDiseases as $disease){
                    $meshId=$disease["ontologyId"];
                    if($meshId==$concept1){
                        $meshId=$concept1;
                        break;
                    }
                }
                if($meshId==""){
                    $error="something went wrong searching meshId for $concept1";
                    ldd($error);
                }
                //ld($meshId);
                $finder2 = $this->container->get('fos_elastica.index.melanomamine.abstracts');
                $elasticaQuery2 = new \Elastica\Query();
                $elasticaQuery2->setSize(500);
                $elasticaQuery2->setSort(array('melanoma_score_2' => array('order' => 'desc')));
                $queryBool2 = new \Elastica\Query\BoolQuery();

                $searchNested2 = new \Elastica\Query\QueryString();
                $searchNested2->setParam('query', $meshId);
                $searchNested2->setParam('fields', array('diseases3.ontologyId'));

                $nestedQuery2 = new \Elastica\Query\Nested();
                $nestedQuery2->setQuery($searchNested2);
                $nestedQuery2->setPath('diseases3');

                $queryBool2->addMust($nestedQuery2);
                $elasticaQuery2->setQuery($queryBool2);
                $data2 = $finder2->search($elasticaQuery2);
                $arrayResults2=$data2->getResults();
                $arrayReturn=array();
                $arrayReturn[0]="diseases";
                $arrayReturn[1]=$arrayResults2;
                return($arrayReturn);
            }else{//It should be a gene. We search for it...
                //Then we search for geneProteinName inside genes dictionary
                $ncbiGeneId="";
                $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
                $elasticaQuery = new \Elastica\Query();
                $elasticaQuery->setSize(1);
                $queryBool = new \Elastica\Query\BoolQuery();
                $queryString = new \Elastica\Query\QueryString();
                $queryString->setParam('query', $concept1);
                $queryString->setParam('fields', array("geneProteinName"));
                $queryBool->addMust($queryString);

                $elasticaQuery->setQuery($queryBool);
                $data = $finder->search($elasticaQuery);
                $arrayResults=$data->getResults();
                if(count($arrayResults)==1){
                    $ncbiGeneId=$arrayResults[0]->getSource()["ncbiGeneId"];
                }
                elseif(count($arrayResults)==0){ //Could concept1 be a ncbiGeneId??
                    $message="Could concept1 be a ncbiGeneId??";

                    if(is_numeric($concept1)){
                        $performFreeTextSearch=false;//Attention!! concept1 could be a numeric input but not a ncbiGeneId!!!!! We use this boolean to take into account this possibility
                        $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
                        $elasticaQuery = new \Elastica\Query();
                        $elasticaQuery->setSize(1);
                        $queryBool = new \Elastica\Query\BoolQuery();
                        $queryString = new \Elastica\Query\QueryString();
                        $queryString->setParam('query', $concept1);
                        $queryString->setParam('fields', array("ncbiGeneId"));
                        $queryBool->addMust($queryString);

                        $elasticaQuery->setQuery($queryBool);
                        $data = $finder->search($elasticaQuery);
                        $arrayResults=$data->getResults();
                        if(count($arrayResults)==1){
                            $ncbiGeneId=$concept1;
                        }
                        else{//Attention!! concept1 could be a numeric input but not a ncbiGeneId!!!!!
                            $performFreeTextSearch=true;
                        }
                    }
                    elseif(!is_numeric($concept1) or ($performFreeTextSearch==true)){
                        $message="It's not a disease nor a gene. We perform a free-text search";

                        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');
                        $elasticaQuery  = new \Elastica\Query();
                        $elasticaQuery->setSize(500);

                        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

                        $queryString  = new \Elastica\Query\QueryString();
                        //'And' or 'Or' default : 'Or'
                        $queryString->setDefaultOperator('AND');
                        $queryString->setQuery($concept1);
                        $elasticaQuery->setQuery($queryString);
                        $data = $finder->search($elasticaQuery);
                        $arrayResults=$data->getResults();
                        $arrayReturn=array();
                        $arrayReturn[0]="freetext";
                        $arrayReturn[1]=$arrayResults;
                        return($arrayReturn);
                    }
                }
                if($ncbiGeneId!=""){//We have a ncbiGeneId to search with, implementing a query expansion
                    $message="We have a ncbiGeneId to search with, implementing a query expansion";
                    $finder3 = $this->container->get('fos_elastica.index.melanomamine.abstracts');
                    $elasticaQuery3 = new \Elastica\Query();
                    $elasticaQuery3->setSize(500);
                    $elasticaQuery3->setSort(array('melanoma_score_2' => array('order' => 'desc')));

                    //BoolQuery to load 2 queries.
                    $queryBool3 = new \Elastica\Query\BoolQuery();

                    //First query to search inside nested genes.mention
                    $searchNested3 = new \Elastica\Query\QueryString();
                    $searchNested3->setParam('query', $ncbiGeneId);
                    $searchNested3->setParam('fields', array('genes3.ontology'));

                    $nestedQuery3 = new \Elastica\Query\Nested();
                    $nestedQuery3->setQuery($searchNested3);
                    $nestedQuery3->setPath('genes3');

                    $queryBool3->addMust($nestedQuery3);
                    $elasticaQuery3->setQuery($queryBool3);
                    $data3 = $finder3->search($elasticaQuery3);
                    $arrayResults3=$data3->getResults();
                    $arrayReturn=array();
                    $arrayReturn[0]="genes";
                    $arrayReturn[1]=$arrayResults3;
                    return($arrayReturn);
                }
            }
        }
        //Now we have to iterate over the values of these results and repeat the search
        $message="Shouldn't get to this point.Debug!";
        ldd($message);

    }

    public function getGeneId($geneName, $ncbiTaxId){
        $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(10);

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();
        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $geneName);
        $queryString->setParam('fields', array('geneProteinName'));

        $queryBool->addMust($queryString);

        //Second query to search inside ncbiTaxId
        $queryString2 = new \Elastica\Query\QueryString();
        $queryString2->setParam('query', $ncbiTaxId);
        $queryString2->setParam('fields', array('ncbiTaxId'));

        $queryBool->addMust($queryString2);

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        if($totalHits == 1){
            $ncbiGeneId=$data->getResults()[0]->getSource()["ncbiGeneId"];
        }elseif($totalHits == 0){
            $errorMessage="There are no geneId for this gene name: $geneName";
            ldd($errorMessage);
        }else{
            $errorMessage="There are more than one geneId for this gene name: $geneName (ncbiTaxId: $ncbiTaxId)";
            ld($data->getResults());
            ldd($errorMessage);
        }
        return($ncbiGeneId);
    }

    public function createSummaryTable($arrayResults, $entityName, $whatToSearch){
        $message="inside createSummaryTable";
        //ld($arrayResults[0]);
        //We have to iterate over results in $arrayResults and generate an structure to handle this information
        //dictionarySummary["genes"]=dictionaryGenes
        //dictionarySummary["mutations"]=dictionaryMutations   ...and so on

        //dictionaryGenes should have :  dictionaryGenes[gene1]=counter1, dictionaryGenes[gene2]=counter2....    .... dictionaryGenes[genen]=countern
        // and so on...
        $dictionarySummary=array();
        $arraySummaryTitles=array();
        $geneList=array();
        $arrayEntrezMention=array();
        $stringCSV="";  //in stringCSV we generate the content of the CSV file that will be downloaded upon user request
        $stringTable="";
        //We take advantage of this loop to also get a list of unique genes to use it for GSE link
        $uniqueGenesList=array();
        foreach($arrayResults as $result){
            $source=$result->getSource();
            $pmid=$source["pmid"];
            $dictionaryTmp=[];
            if ( array_key_exists("genes3", $source) ){
                $arrayGenes=$source["genes3"];
                #ld($arrayGenes);
                foreach($arrayGenes as $gene){
                    $mention=$gene["mention"];
                    $ontologyId=$gene["ontologyId"];
                    $geneIdString=$gene["ontology"];//ncbiGeneId
                    $arrayGeneId=explode(",", $geneIdString);
                    foreach($arrayGeneId as $geneId){
                        //ld($geneId);
                        $dictionarySummary=$this->insertIdMentions($dictionarySummary,"genes3", $geneId, $mention);
                        $dictionaryTmp=$this->insertMention($dictionaryTmp,"genes", $geneId);
                    }
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"genes", $mention);

                    foreach($arrayGeneId as $geneId){
                        array_push($uniqueGenesList, $geneId);
                        if (array_key_exists($geneId, $arrayEntrezMention)){
                            //We update $arrayEntrezMention with another geneId
                            $arrayTmp=$arrayEntrezMention[$geneId];
                            if(!in_array($mention, $arrayTmp)){
                                array_push($arrayTmp, $mention);//We just keep the mention if it has not been added before
                                $arrayEntrezMention[$geneId]=$arrayTmp;
                            }
                        }else{
                            //We create a new entry for this geneId
                            $arrayTmp=array();
                            array_push($arrayTmp, $mention);
                            $arrayEntrezMention[$geneId]=$arrayTmp;
                        }
                    }
                }
            }
            if ( array_key_exists("diseases3", $source) ){
                $arrayDiseases=$source["diseases3"];
                foreach($arrayDiseases as $disease){
                    $mention=$disease["mention"];
                    $meshId=$disease["ontologyId"];

                    $dictionarySummary=$this->insertIdMentions($dictionarySummary,"diseases3", $meshId,$mention);
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"diseases", $mention);
                }
            }
            if ( array_key_exists("mutations2", $source) ){
                $arrayMutations=$source["mutations2"];
                foreach($arrayMutations as $mutation){
                    $mention=$mutation["mention"];
                    $dictionarySummary=$this->insertMention($dictionarySummary,"mutations2", $mention);
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"mutations", $mention);
                }
            }
            if ( array_key_exists("chemicals2", $source) ){
                $arrayChemicals=$source["chemicals2"];
                foreach($arrayChemicals as $chemical){
                    $mention=$chemical["mention"];
                    $dictionarySummary=$this->insertMention($dictionarySummary,"chemicals2", $mention);
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"chemicals", $mention);
                }
            }
            if ( array_key_exists("mutatedProteins4", $source) ){
                $mutatedProteins=$source["mutatedProteins4"];
                foreach($mutatedProteins as $mutatedProtein){
                    $mention=$mutatedProtein["mention"];
                    $dictionarySummary=$this->insertMention($dictionarySummary,"mutatedProteins4", $mention);
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"mutatedProteins", $mention);
                }
            }
            if ( array_key_exists("species2", $source) ){
                $species=$source["species2"];
                foreach($species as $specie){
                    $mention=$specie["mention"];
                    $dictionarySummary=$this->insertMention($dictionarySummary,"species2", $mention);
                    $dictionaryTmp=$this->insertMention($dictionaryTmp,"species", $mention);
                }
            }

            arsort($dictionaryTmp);
            $arraySummaryTitles[$pmid]=$dictionaryTmp;
        }
        //if(array_key_exists("genes3", $dictionarySummary)){
        //    $geneList=$dictionarySummary["genes3"];
        //}
        $uniqueGenesList=array_unique($uniqueGenesList);
        //We kepp $uniqueGenesList in the session so we can access later at the GSE process.
        $session = $this->getRequest()->getSession();
        // store an attribute for reuse during a later user request
        $session->set('uniqueGenesList', $uniqueGenesList);
        //ldd($session);
        $session->set('arrayEntrezMention', $arrayEntrezMention);



        /*$urlToGSE= $this->generateUrl( //we generate the link for the Gene Set Enrichment workflow
            'gene_set_enrichment',
            array('entityName' => $entityName, 'geneList' => $uniqueGenesList)
        );*/
        $url= $this->generateUrl( //we generate the link for the Gene Set Enrichment workflow
                'gene_set_enrichment', array('whatToSearch' => $whatToSearch,'entityName' => $entityName)
            );

        //We have to short inner dictionaries and create the stringTable to return

        if(count($dictionarySummary)!=0){
            $stringTable="<table class='summaryTable'>";
            //ld($dictionarySummary);
            if ( array_key_exists("genes3", $dictionarySummary) ){
                $arrayGeneIdDictionary=$dictionarySummary["genes3"];
                arsort($arrayGeneIdDictionary);
                $stringTable.="<tr><th>
                                        <span class='genes_highlight'>Genes</span>
                                        <br/>";
                if($whatToSearch!="knowledge"){
                    $stringTable.="<a href='$url' target='_blank'>GSE</a>";
                }
                $stringTable.="</th>";
                $stringCSV.="### Genes ###
                             ### geneId:(number of times)###\n";
                $stringTable.=" <td class='summaryTable'><span class='more'>";
                //First we generate a new array to sort the results based on the count of mentions of every geneId
                $arraySum=array();
                $arrayNames=array();
                foreach ($arrayGeneIdDictionary as $geneId => $geneIdDictionary){
                        $counterMentions=$this->countMentionsInArray($geneIdDictionary);
                        $arraySum[$geneId]=$counterMentions;
                }
                arsort($arraySum);

                foreach($arraySum as $idGene=>$counter){
                    $name=array_keys($arrayGeneIdDictionary[$idGene])[0];
                    $stringTable.="$idGene ($name: $counter), "; //To get the name for the geneId. We choose the first one inside the arrayGeneIdDictionary
                    $stringCSV.="$idGene ($name: $counter), ";

                }
                    //We take advantage of this loop to also get a list of unique genes to use it for GSE link ($uniqueGenesList)
                    //if(!array_key_exists($key, $uniqueGenesList)){
                    //    $messageEntra="No entra, la key no existe!";
                    //    array_push($uniqueGenesList, $key);
                    //}  //Not needed since it can be retrieved from dictionarySummery['genes3']
                $stringTable=substr($stringTable, 0, -2);
                $stringCSV=substr($stringCSV, 0, -2);
                $stringTable.="</span></td></tr/>";
                $stringCSV.="\n";
            }
            if ( array_key_exists("diseases3", $dictionarySummary) ){
                $arrayDiseases=$dictionarySummary["diseases3"];
                arsort($arrayDiseases);

                $stringCSV.="### Diseases ###
                             ### meshId:(number of times)###\n";
                $stringTable.="<tr><th><span class='diseases_highlight'>Diseases</span></th><td><span class='more'>";
                $arraySum=array();

                foreach ($arrayDiseases as $meshId => $arrayDiseasesMentions ){
                    $counterMentions=$this->countMentionsInArray($arrayDiseasesMentions);
                    $arraySum[$meshId]=$counterMentions;
                }
                arsort($arraySum);
                foreach($arraySum as $meshId=>$counter){
                    $stringTable.="$meshId:($counter), ";
                    $stringCSV.="$meshId:($counter), ";
                }
                $stringTable=substr($stringTable, 0, -2);
                $stringCSV=substr($stringCSV, 0, -2);
                $stringTable.="</span></td></tr>";
                $stringCSV.="\n";
            }
            if ( array_key_exists("mutations2", $dictionarySummary) ){
                $arrayMutations=$dictionarySummary["mutations2"];
                arsort($arrayMutations);

                $stringTable.="<tr><th><span class='mutations_highlight'>Mutations</span></th><td><span class='more'>";
                $stringCSV.="MUTATIONS\tAppearances\n";
                foreach ($arrayMutations as $key => $value){
                    $stringTable.="$key: $value, ";
                    $stringCSV.="$key\t$value\n";
                }
                $stringTable.="</span></td></tr>";
                $stringCSV.="\n";
            }
            if ( array_key_exists("chemicals2", $dictionarySummary) ){
                $arrayChemicals=$dictionarySummary["chemicals2"];
                arsort($arrayChemicals);

                $stringTable.="<tr><th><span class='chemicals_highlight'>Chemicals</span></th><td><span class='more'>";
                $stringCSV.="CHEMICALS\tAppearances\n";
                foreach ($arrayChemicals as $key => $value){
                    $stringTable.="$key: $value, ";
                    $stringCSV.="$key\t$value\n";
                }
                $stringTable.="</span></td></tr>";
                $stringCSV.="\n";
            }
            if ( array_key_exists("mutatedProteins4", $source) ){
                $arrayMutatedProteins=$dictionarySummary["mutatedProteins4"];
                arsort($arrayMutatedProteins);

                $stringTable.="<tr><th><span class='mutatedProteins_highlight'>Mutated Proteins</span></th><td><span class='more'>";
                $stringCSV.="MUTATED PROTEINS\tAppearances\n";
                foreach ($arrayMutatedProteins as $key => $value){
                    $stringTable.="$key: $value, ";
                    $stringCSV.="$key\t$value\n";
                }
                $stringTable.="</span></td></tr>";
                $stringCSV.="\n";
            }
            if ( array_key_exists("species2", $source) ){
                $arraySpecies=$dictionarySummary["species2"];
                arsort($arraySpecies);

                $stringTable.="<tr><th><span class='species_highlight'>Species</span></th><td><span class='more'>";
                $stringCSV.="SPECIES\tAppearances\n";
                foreach ($arraySpecies as $key => $value){
                    $stringTable.="$key: $value, ";
                    $stringCSV.="$key\t$value\n";
                }
                $stringTable.="</span></td></tr>";
                $stringCSV.="\n";
            }

            $stringTable.="</table>";
        }

        //We create the file with the CSV content
        $zip = new \ZipArchive();
        $path = $this->get('kernel')->getRootDir(). "/../web/files/summaryTables";
        $date=date("Y-m-d_H:i:s");
        $filename = $entityName."-".$date;
        $pathToFile="$path/$filename";
        $pathToZip="$pathToFile.zip";

        if ($zip->open($pathToZip, \ZIPARCHIVE::CREATE )!==TRUE) {
            exit("cannot open <$pathToZip>\n");
        }
        $fp = fopen($pathToFile, 'w');
        fwrite($fp, $stringCSV);
        fclose($fp);
        $zip->addFile($pathToFile,$filename.".csv");
        $zip->close();

        unlink($pathToFile);
        $filename=$filename.".zip";
        //Delete filename keeping only .zip


        $arrayResponse=[];
        $arrayResponse["filename"]=$filename;
        $arrayResponse["summaryTable"]=$stringTable;
        $arrayResponse["summaryTitles"]=$arraySummaryTitles;
        $arrayResponse["dictionarySummary"]=$dictionarySummary;

        return $arrayResponse;
    }

    public function getAliases($ncbiGeneId, $queryType){
        $message="inside getAliases";
        $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $ncbiGeneId);
        $queryString->setParam('fields', array('ncbiGeneId'));
        $elasticaQuery->setQuery($queryString);
        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $arrayResults=$data->getResults();
        //Now we generate an array with the Aliases:
        $arrayAliases=[];
        foreach($arrayResults as $result){
            $source=$result->getSource();
            array_push($arrayAliases, $source["geneProteinName"]);
        }
        return($arrayAliases);
    }

    public function disambiguationProcess($entityName, $whatToSearch, $specie, $queryType)
    {
        $message="inside getArrayQueryExpansion";
        if($queryType=="geneProtein"){
            $entityType="genes";
        }elseif($queryType=="proteinMutated"){
            $entityType="mutatedProteins";
        }
        $finder = $this->container->get('fos_elastica.index.melanomamine.genesDictionary');

        $elasticaQuery = new \Elastica\Query();
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $entityName);
        $queryString->setParam('fields', array('geneProteinName'));

        $queryBool->addMust($queryString);

        /*
        //Second query to search inside ncbiTaxId
        $queryString2 = new \Elastica\Query\QueryString();
        $queryString2->setParam('query', 9606);
        $queryString2->setParam('fields', array('ncbiTaxId'));

        $queryBool->addMust($queryString2);
        */
        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayResults=$data->getResults();


        //We generate an structure for disambiguation interface. Including Name-ncbiGenId-Array-of-Aliases.

        $arrayNameIdAliases=[];
        foreach($arrayResults as $result){
            $source=$result->getSource();
            $ncbiGeneId=$source["ncbiGeneId"];
            $tmpDictionary["geneProteinName"]=$source["geneProteinName"];
            $tmpDictionary["ncbiGeneId"]=$ncbiGeneId;
            $tmpDictionary["ncbiTaxId"]=$source["ncbiTaxId"];
            $arrayAliases=$this->getAliases($ncbiGeneId, $queryType);
            $tmpDictionary["arrayAliases"]=$arrayAliases;

            array_push($arrayNameIdAliases, $tmpDictionary);
        }
        //If theres is only one element inside arrayNameIdAliases, There is no need for disambiguation
        $message="Go to disambiguation interface";
        return $this->render('MelanomamineFrontendBundle:Search:disambiguation.html.twig', array(
            'entityName' => $entityName,
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'arrayNameIdAliases' => $arrayNameIdAliases,
            'specie' => $specie,

        ));



        //En $ncbiGeneId tenemos el id para realizar la búsqueda de la query expansion...

        /*
        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $ncbiGeneId);
        $queryString->setParam('fields', array('ncbiGeneId'));

        $queryBool->addMust($queryString);

        //Second query to search inside ncbiTaxId
        $queryString2 = new \Elastica\Query\QueryString();
        $queryString2->setParam('query', 9606);
        $queryString2->setParam('fields', array('ncbiTaxId'));

        $queryBool->addMust($queryString2);

        $elasticaQuery->setQuery($queryBool);
        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        ld($totalHits);
        $totalTime = $data->getTotalTime();
        $arrayResults=$data->getResults();

        //We generate
        ldd($arrayGenes);
        */

    }



    public function getChemicalsQueryExpansion($entityName){
        $message="inside getChemicalsQueryExpansion";
        $arrayAliases=[];
        array_push($arrayAliases, $entityName);
        //ld($entityName);
        $entityName = $this->escapeElasticReservedChars($entityName);
        $finder = $this->container->get('fos_elastica.index.melanomamine.chemicalsDictionary');
        $entityType="chemicals";


        $elasticaQuery = new \Elastica\Query();
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside geneProteinName
        $queryString = new \Elastica\Query\QueryString();
        $queryString->setParam('query', $entityName);
        $queryString->setParam('fields', array('chemicalName'));

        $queryBool->addMust($queryString);

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayResults=$data->getResults();
        //Now we have to iterate over the values of these results and repeat the search. We focus in chebiId field

        foreach($arrayResults as $result){
            $chebi = $result->getSource()["chebi"];
            //ld($chebi);
            if( $chebi != ""){
                $data=$this->performBasicSearch($chebi,"chebi","chemicalsDictionary");
                $arrayResults=$data->getResults();
                //$arrayTmp=$this->performBasicSearch("vemurafenib","chemicalName","chemicalsDictionary");
                //$arrayTmp=$this->performBasicSearch("CHEBI\:63637","chebi","chemicalsDictionary");
                //ld($arrayTmp);
                foreach($arrayResults as $tmpResult){
                    //ld($tmpResult);
                    $alias=$tmpResult->getSource()["chemicalName"];
                    array_push($arrayAliases, $alias);
                }
            }
        }
        //ld($arrayAliases);
        //Delete duplicates:
        $arrayAliases=array_unique($arrayAliases);
        return $arrayAliases;
    }

    public function setSortScore($orderBy){
        switch ($orderBy){
            case "bladder":
                $orderBy = "bladder_score";
                break;
            case "breast":
                $orderBy = "breast_score";
                break;
            case "glioblastome":
                $orderBy = "glioblastoma_score";
                break;
            case "hcc":
                $orderBy = "hcc_score";
                break;
            case  "nsclc":
                $orderBy = "nsclc_score_2";
                break;
            case "melanome":
                $orderBy = "melanoma_score_2";
                break;
            case  "pancreas":
                $orderBy = "pancreas_score";
                break;
            case  "prostate":
                $orderBy = "prostate_score";
                break;
        }

        return $orderBy;
    }

    public function searchKeywordsAction($whatToSearch, $entityName, $orderBy)
    {

        $entityType="keywords";
        $message="inside searchKeywordAction";

        $orderBy=$this->setSortScore($orderBy);

        $elasticaQuery  = new \Elastica\Query();
        $elasticaQuery->setSize(500);

        $elasticaQuery->setSort(array($orderBy => array('order' => 'desc')));

        $queryString  = new \Elastica\Query\QueryString();
        //'And' or 'Or' default : 'Or'
        $queryString->setDefaultOperator('AND');
        $queryString->setQuery($entityName);

        if($whatToSearch=="freeText"){


            $elasticaQuery->setQuery($queryString);

        }elseif($whatToSearch=="withGenesProtein"){

            $field = "genes2";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withProteinMutations"){

            $field = "mutatedProteins4";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withSNPs"){

            $field = "snps";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withDNAmutations"){

            $field = "mutations";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withChemicals"){

            $field = "chemicals";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withDiseases"){

            $field = "diseases2";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }



        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();


        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
    ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);

        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => $orderBy,
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,

        ));
    }

    public function searchGenesAction($whatToSearch, $entityName, $specie)
    {
        $entityType="genes";
        $message="inside searchGenesAction";
        if ($whatToSearch=="geneProteinName"){
            #First of all we check for query expansion. But we only do this in geneProteinName searches and never for ncbiGeneId
            if ($specie=="queryExpansion"){
                return $arrayQueryExpanded=$this->disambiguationProcess($entityName, $whatToSearch, $specie, "geneProtein");
                ##########################################################################################
                ##############Stops here going through queryExpansion disambiguation process##############
                ##########################################################################################
            }
            $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

            $elasticaQuery = new \Elastica\Query();
            $elasticaQuery->setSize(500);
            $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

            //BoolQuery to load 2 queries.
            $queryBool = new \Elastica\Query\BoolQuery();

            //First query to search inside nested genes.mention
            $searchNested = new \Elastica\Query\QueryString();
            $searchNested->setParam('query', $entityName);
            $searchNested->setParam('fields', array('genes3.mention'));

            $nestedQuery = new \Elastica\Query\Nested();
            $nestedQuery->setQuery($searchNested);
            $nestedQuery->setPath('genes3');

            $queryBool->addMust($nestedQuery);
            $elasticaQuery->setQuery($queryBool);

            if ($specie=="all"){
                //No need for specie restriction:
                $data = $finder->search($elasticaQuery);
                $totalHits = $data->getTotalHits();
                $totalTime = $data->getTotalTime();
                $arrayAbstracts=$data->getResults();


            }else{
                //We need specie restriction
                if ($specie=="human"){
                    $specie="9606";
                }
                //Second query to search inside nested genes.ontologyId to see if it's a human gene
                $searchNested2 = new \Elastica\Query\QueryString();
                $searchNested2->setParam('query', $specie);
                $searchNested2->setParam('fields', array('genes3.ontologyId'));

                $nestedQuery2 = new \Elastica\Query\Nested();
                $nestedQuery2->setQuery($searchNested2);
                $nestedQuery2->setPath('genes3');

                $queryBool->addMust($nestedQuery2);

                $elasticaQuery->setQuery($queryBool);


                $data = $finder->search($elasticaQuery);
                $totalHits = $data->getTotalHits();
                $totalTime = $data->getTotalTime();
                $arrayAbstracts=$data->getResults();
            }
        }
        elseif($whatToSearch=="ncbiGeneId"){
            $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

            $elasticaQuery = new \Elastica\Query();
            $elasticaQuery->setSize(500);
            $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

            //BoolQuery to load 2 queries.
            $queryBool = new \Elastica\Query\BoolQuery();

            //First query to search inside nested genes.mention
            $searchNested = new \Elastica\Query\QueryString();
            $searchNested->setParam('query', $entityName);
            $searchNested->setParam('fields', array('genes3.ontology'));

            $nestedQuery = new \Elastica\Query\Nested();
            $nestedQuery->setQuery($searchNested);
            $nestedQuery->setPath('genes3');

            $queryBool->addMust($nestedQuery);

            //Second query to search inside nested genes.ontologyId to see if it's a human gene
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('query', 9606);
            $searchNested2->setParam('fields', array('genes3.ontologyId'));

            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('genes3');
            if ($specie=="human"){
                $queryBool->addMust($nestedQuery2);
            }

            $elasticaQuery->setQuery($queryBool);


            $data = $finder->search($elasticaQuery);
            $totalHits = $data->getTotalHits();
            $totalTime = $data->getTotalTime();
            $arrayAbstracts=$data->getResults();

        }

        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
    ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);
        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'specie' => $specie,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));
    }


    public function searchGenesExpandedAction($whatToSearch, $entityName, $specie, $searchTerm)
    {
        $entityType="genes";
        $message="inside searchGenesExpandedAction";
        //At this time, $entityName contains the ncbiGeneId
        $arrayAliases=$this->getAliases($entityName, "whatever?");

        //Even though the entityName contains the ncbiGeneId, this is not the term that the user has searched for. We need to get it in order to show it at results template


        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $searchNested->setParam('query', $entityName);
        $searchNested->setParam('fields', array('genes3.ontology')); //shouldn't be ontology field!! Re-insert data into genes3. ncbiGeneId

        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        $nestedQuery->setPath('genes3');

        $queryBool->addMust($nestedQuery);

        $elasticaQuery->setQuery($queryBool);


        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();

        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
    ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        return $this->render('MelanomamineFrontendBundle:Search:results_query_expanded.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'searchTerm' => $searchTerm,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'totalHits' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'specie' => $specie,
            'totalTime' => $totalTime,
            'arrayAliases' => $arrayAliases,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));
    }


    public function searchMutationsAction($whatToSearch, $entityName, $dna, $protein)
    {
        $message="inside searchMutationsAction";

        $entityType="mutations";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $searchNested->setParam('query', $entityName);

        if($whatToSearch=="snps"){
            $searchNested->setParam('fields', array('snps.mention'));
        }else{
            $searchNested->setParam('fields', array('mutations2.mention'));
        }

        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        if($whatToSearch=="snps"){
            $nestedQuery->setPath('snps');
        }else{
            $nestedQuery->setPath('mutations2');
        }

        $queryBool->addMust($nestedQuery);

        if($whatToSearch!="snps"){
            //Second query to search for the type of mutation:
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('fields', array('mutations2.mutationClass'));
            if($whatToSearch=="substitutions"){
                $searchNested2->setParam('query', "Substitution");
            }elseif($whatToSearch=="insertions"){
                $searchNested2->setParam('query', "Insertion");
            }elseif($whatToSearch=="deletions"){
                $searchNested2->setParam('query', "Deletion");
            }elseif($whatToSearch=="indels"){
                $searchNested2->setParam('query', "InDel");
            }elseif($whatToSearch=="frameshifts"){
                $searchNested2->setParam('query', "Frame_shift");
            }
            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('mutations2');

            $queryBool->AddMust($nestedQuery2);

        }
        //We need a third query to search for dna/protein mutations
        /*
        if($whatToSearch!="snps"){
            //Second query to search for the type of mutation:
            $searchNested3 = new \Elastica\Query\QueryString();
            $searchNested3->setParam('fields', array('mutations.sequenceClass'));

            $searchNested3->setParam('query', "Frame_shift");
            $nestedQuery3 = new \Elastica\Query\Nested();
            $nestedQuery3->setQuery($searchNested3);
            $nestedQuery3->setPath('mutations');

            $queryBool->AddMust($nestedQuery3);

        }*/

        $elasticaQuery->setQuery($queryBool);



        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();

        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);


        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'dna' => $dna,
            'protein' => $protein,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchNormalizedProteinMutationsAction( $normalizedWildType, $normalizedPosition, $normalizedMutant)
    {
        $message="inside searchNormalizedProteinsMutationsAction";
        $entityType="mutations";
        $whatToSearch="whatToSearch";
        $dna="false";
        $protein="true";
        $normalizedWildType=strtoupper($normalizedWildType);
        $normalizedPosition=strtoupper($normalizedPosition);
        $normalizedMutant=strtoupper($normalizedMutant);
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        if($normalizedWildType!="NONE"){
            $searchNested = new \Elastica\Query\QueryString();
            $searchNested->setParam('query', $normalizedWildType);
            $searchNested->setParam('fields', array('mutations2.wildType'));
            $nestedQuery = new \Elastica\Query\Nested();
            $nestedQuery->setQuery($searchNested);
            $nestedQuery->setPath('mutations2');
            $queryBool->addMust($nestedQuery);
        }
        if($normalizedPosition!="NONE"){
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('query', $normalizedPosition);
            $searchNested2->setParam('fields', array('mutations2.position'));
            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('mutations2');
            $queryBool->AddMust($nestedQuery2);
        }
        if($normalizedMutant!="NONE"){
            $searchNested3 = new \Elastica\Query\QueryString();
            $searchNested3->setParam('query', $normalizedMutant);
            $searchNested3->setParam('fields', array('mutations2.mutant'));
            $nestedQuery3 = new \Elastica\Query\Nested();
            $nestedQuery3->setQuery($searchNested3);
            $nestedQuery3->setPath('mutations2');
            $queryBool->AddMust($nestedQuery3);
        }

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();
        //$entityName for createSummaryTable is only needed for filename creation. Here we set a nonsense value as entityName but works for filename creation.
        $entityName="Normalized Protein Mutations Search";
        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);


        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'dna' => $dna,
            'protein' => $protein,
            'normalizedWildType' => $normalizedWildType,
            'normalizedPosition' => $normalizedPosition,
            'normalizedMutant' => $normalizedMutant,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchNormalizedMutatedProteinsAction( $normalizedWildType, $normalizedPosition, $normalizedMutant)
    {
        $message="inside searchNormalizedMutatedProteinsAction";
        $entityType="mutatedProteins";
        $whatToSearch="whatToSearch";
        $normalizedWildType=strtoupper($normalizedWildType);
        $normalizedPosition=strtoupper($normalizedPosition);
        $normalizedMutant=strtoupper($normalizedMutant);
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        if($normalizedWildType!="NONE"){
            $searchNested = new \Elastica\Query\QueryString();
            $searchNested->setParam('query', $normalizedWildType);
            $searchNested->setParam('fields', array('mutatedProteins4.wildType_aa'));
            $nestedQuery = new \Elastica\Query\Nested();
            $nestedQuery->setQuery($searchNested);
            $nestedQuery->setPath('mutatedProteins4');
            $queryBool->addMust($nestedQuery);
        }
        if($normalizedPosition!="NONE"){
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('query', $normalizedPosition);
            $searchNested2->setParam('fields', array('mutatedProteins4.startMutation'));
            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('mutatedProteins4');
            $queryBool->AddMust($nestedQuery2);
        }
        if($normalizedMutant!="NONE"){
            $searchNested3 = new \Elastica\Query\QueryString();
            $searchNested3->setParam('query', $normalizedMutant);
            $searchNested3->setParam('fields', array('mutatedProteins4.mutant_aa'));
            $nestedQuery3 = new \Elastica\Query\Nested();
            $nestedQuery3->setQuery($searchNested3);
            $nestedQuery3->setPath('mutatedProteins4');
            $queryBool->AddMust($nestedQuery3);
        }

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();
        //$entityName for createSummaryTable is only needed for filename creation. Here we set a nonsense value as entityName but works for filename creation.
        $entityName="Normalized Mutated Proteins Search";
        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);


        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'normalizedWildType' => $normalizedWildType,
            'normalizedPosition' => $normalizedPosition,
            'normalizedMutant' => $normalizedMutant,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchChemicalsAction($whatToSearch, $entityName, $queryExpansion)
    {
        $entityType="chemicals";
        $message="inside searchChemicalsAction";
        //ldd($queryExpansion);
        $searchTerm=$entityName;
        # Desambiguation is not needed for Chemical queryExpansion.
        if($queryExpansion=="true"){
            #In this case we make a query expansion for the chemicalName and retrieve an array of entityNames to search form
            $arrayEntityName=$this->getChemicalsQueryExpansion($entityName);
            //ld($arrayEntityName);
            //We should retrieve al the abstracts for any of the entityName inside the $arrayEntityName
            $arrayAbstracts=[];
            foreach($arrayEntityName as $entityName){
                $tmpResults=$this->performNestedSearch($entityName, "abstracts", "chemicals2", "mention");//performNestedSearch($entityName, $type, $mapping, $property)
                $arrayAbstracts=array_merge($arrayAbstracts,$tmpResults);
            }
            $entityName=$searchTerm;
            $totalHits=count($arrayAbstracts);
            $totalTime=0;

            //$arrayResponse=[];
            //$filename="vemurafenib-2015-12-14_18:26:18.zip";
            $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
            $filename = $arrayResponse["filename"];
            $summaryTable = $arrayResponse["summaryTable"];
            $arraySummaryTitles = $arrayResponse["summaryTitles"];
            $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

            $paginator = $this->get('ideup.simple_paginator');
            $arrayResultsAbs = $paginator
                //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
                ->setMaxPagerItems(15, 'abstracts')
                //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
                ->setItemsPerPage(10, 'abstracts')
                ->paginate($arrayAbstracts,'abstracts')
                ->getResult()
            ;
            ############### Uncomment when a SCORE has been added to the elasticsearch entries
            //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
            //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
            //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
            //$finderDoc=false;
            ############### Comment when a SCORE has been added to the elasticsearch entries
            $meanScore=0;
            $medianScore=0;
            $rangeScore=0;

            $resultSetDocuments = array();
            $arrayResultsDoc = array();
            $entityName=$searchTerm;
            //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);
            return $this->render('MelanomamineFrontendBundle:Search:results_query_expanded.html.twig', array(
                'entityType' => $entityType,
                'whatToSearch' => $whatToSearch,
                'entityName' => $entityName,
                'searchTerm' => $searchTerm,
                'arrayResultsAbs' => $arrayResultsAbs,
                'arrayResultsDoc' => $arrayResultsDoc,
                'resultSetDocuments' => $resultSetDocuments,
                'entityName' => $entityName,
                'orderBy' => "score",
                'hitsShowed' => $totalHits,
                'meanScore' => $meanScore,
                'medianScore' => $medianScore,
                'rangeScore' => $rangeScore,
                'totalTime' => $totalTime,
                'totalHits' => $totalHits,
                'queryExpansion' => $queryExpansion,
                'summaryTable' => $summaryTable,
                'filenameSummaryTable' => $filename,
                'arraySummaryTitles' => $arraySummaryTitles,
            ));


        }elseif($queryExpansion=="false"){
            #We just search for entityName. No query expansion needeed
            $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

            $elasticaQuery = new \Elastica\Query();
            $elasticaQuery->setSize(500);
            $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));
            //BoolQuery to load 2 queries.
            $queryBool = new \Elastica\Query\BoolQuery();

            //First query to search inside nested genes.mention
            $searchNested = new \Elastica\Query\QueryString();
            //We escape entityName but keep it into another variable "searchName" in order to not modify entityName. It will be passed later on to template.
            $searchName = $this->escapeElasticReservedChars($entityName);
            $searchNested->setParam('query', $searchName);
            $searchNested->setParam('fields', array('chemicals2.mention'));

            $nestedQuery = new \Elastica\Query\Nested();
            $nestedQuery->setQuery($searchNested);
            $nestedQuery->setPath('chemicals2');

            $queryBool->addMust($nestedQuery);

            $elasticaQuery->setQuery($queryBool);


            $data = $finder->search($elasticaQuery);
            $totalHits = $data->getTotalHits();
            $totalTime = $data->getTotalTime();
            $arrayAbstracts=$data->getResults();
        }

        //$arrayResponse["stringTable"]="";
        //$filename="vemurafenib-2015-12-14_18:26:18.zip";

        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);

        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchDiseasesAction($whatToSearch, $entityName)
    {
        $message="inside searchDiseasesAction";
        $entityType="diseases";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));
        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $searchNested->setParam('query', $entityName);

        if($whatToSearch=="name"){
            $searchNested->setParam('fields', array('diseases3.mention'));
        }else{
            $searchNested->setParam('fields', array('diseases3.ontologyId'));
        }

        //if whatToSearch is meshId or OMMIMid then we have to set another nested search for the ontology


        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        $nestedQuery->setPath('diseases3');

        $queryBool->addMust($nestedQuery);

        //Second nested query only in case an ontology is been queried
        if ($whatToSearch != "name"){
            if($whatToSearch=="ommimId"){
                $ontology="OMIM";
            }elseif($whatToSearch=="meshId"){
                $ontology="MESH";
            }
            $searchNested2 = new \Elastica\Query\QueryString();
            $searchNested2->setParam('query', $ontology);

            $searchNested2->setParam('fields', array('diseases3.ontology'));

            //if whatToSearch is meshId or OMMIMid then we have to set another nested search for the ontology

            $nestedQuery2 = new \Elastica\Query\Nested();
            $nestedQuery2->setQuery($searchNested2);
            $nestedQuery2->setPath('diseases3');

            $queryBool->addMust($nestedQuery2);
        }


        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();

        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);

        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchMutatedProteinsAction($whatToSearch, $entityName, $specie)
    {
        $message = "inside searchMutatedProteinsAction";
        if($whatToSearch == "proteinName" && $specie == "queryExpansion"){
            return $arrayQueryExpanded=$this->disambiguationProcess($entityName, $whatToSearch, $specie, "proteinMutated");
        }
        $message="inside searchMutatedProteinsAction";
        $entityType="mutatedProteins";
        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $elasticaQuery = new \Elastica\Query();
        $elasticaQuery->setSize(500);
        $elasticaQuery->setSort(array('melanoma_score_2' => array('order' => 'desc')));

        //BoolQuery to load 2 queries.
        $queryBool = new \Elastica\Query\BoolQuery();

        //First query to search inside nested genes.mention
        $searchNested = new \Elastica\Query\QueryString();
        $searchNested->setParam('query', $entityName);
        if($whatToSearch=="proteinName"){
            $searchNested->setParam('fields', array('mutatedProteins4.geneMention'));
        }elseif($whatToSearch=="mutationName"){
            $searchNested->setParam('fields', array('mutatedProteins4.mention'));
        }elseif($whatToSearch=="uniprotAccession"){
            $searchNested->setParam('fields', array('mutatedProteins4.uniprotAccession'));
        }elseif($whatToSearch=="geneId"){
            $searchNested->setParam('fields', array('mutatedProteins4.ncbiGenId'));
        }


        $nestedQuery = new \Elastica\Query\Nested();
        $nestedQuery->setQuery($searchNested);
        $nestedQuery->setPath('mutatedProteins4');

        $queryBool->addMust($nestedQuery);

        if ($specie=="human"){
            $specie="9606";
        }

        //Second query to search inside nested genes.ontologyId to see if it's a human gene
        $searchNested2 = new \Elastica\Query\QueryString();
        $searchNested2->setParam('query', $specie);
        $searchNested2->setParam('fields', array('mutatedProteins4.ncbiTaxId'));

        $nestedQuery2 = new \Elastica\Query\Nested();
        $nestedQuery2->setQuery($searchNested2);
        $nestedQuery2->setPath('mutatedProteins4');

        if ($specie=="human"){
            $queryBool->addMust($nestedQuery2);
        }

        $elasticaQuery->setQuery($queryBool);

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();


        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
        ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();
        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);

        return $this->render('MelanomamineFrontendBundle:Search:results.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => "score",
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'totalTime' => $totalTime,
            'specie' => $specie,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,
        ));

    }

    public function searchOtherCancerAction($whatToSearch, $entityName, $orderBy)
    {

        $entityType="otherCancer";
        $message="inside searchOtherCancerAction";
        $orderBy=$this->setSortScore($orderBy);

        $elasticaQuery  = new \Elastica\Query();
        $elasticaQuery->setSize(500);

        $elasticaQuery->setSort(array($orderBy => array('order' => 'desc')));

        $queryString  = new \Elastica\Query\QueryString();
        //'And' or 'Or' default : 'Or'
        $queryString->setDefaultOperator('AND');
        $queryString->setQuery($entityName);

        if($whatToSearch=="freeText"){


            $elasticaQuery->setQuery($queryString);

        }elseif($whatToSearch=="withGenesProtein"){

            $field = "genes2";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withProteinMutations"){

            $field = "mutatedProteins4";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withSNPs"){

            $field = "snps";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withDNAmutations"){

            $field = "mutations";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withChemicals"){

            $field = "chemicals";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }elseif($whatToSearch=="withDiseases"){

            $field = "diseases2";//We search in type with snowball analyzer to perform typical keyword search
            $filter = new \Elastica\Filter\Exists($field);
            $filteredQuery = new \Elastica\Query\Filtered($queryString, $filter);
            $elasticaQuery->setQuery($filteredQuery);

        }



        $finder = $this->container->get('fos_elastica.index.melanomamine.abstracts');

        $data = $finder->search($elasticaQuery);
        $totalHits = $data->getTotalHits();
        $totalTime = $data->getTotalTime();
        $arrayAbstracts=$data->getResults();


        $arrayResponse = $this->createSummaryTable($arrayAbstracts, $entityName, $whatToSearch); //this method returns an array with two contents: the filename where the summaryTable file is saved, and the string with the summaryTable
        $filename = $arrayResponse["filename"];
        $summaryTable = $arrayResponse["summaryTable"];
        $arraySummaryTitles = $arrayResponse["summaryTitles"];
        $dictionarySummary = $arrayResponse["dictionarySummary"]; //In $geneList we have an associative array with the keys = genes comentioned and the  value = the number of comentions of each one

        $paginator = $this->get('ideup.simple_paginator');
        $arrayResultsAbs = $paginator
            //->setMaxPagerItems($this->container->getParameter('etoxMicrome.number_of_pages'), 'abstracts')
            ->setMaxPagerItems(15, 'abstracts')
            //->setItemsPerPage($this->container->getParameter('etoxMicrome.evidences_per_page'), 'abstracts')
            ->setItemsPerPage(10, 'abstracts')
            ->paginate($arrayAbstracts,'abstracts')
            ->getResult()
        ;
    ############### Uncomment when a SCORE has been added to the elasticsearch entries
        //$meanScore=$this->getMmmrScore($data, 'score', 'mean');
        //$medianScore=$this->getMmmrScore($data, $orderBy, 'median');
        //$rangeScore=$this->getMmmrScore($data, $orderBy, 'range');
        //$finderDoc=false;
        ############### Comment when a SCORE has been added to the elasticsearch entries
        $meanScore=0;
        $medianScore=0;
        $rangeScore=0;

        $resultSetDocuments = array();
        $arrayResultsDoc = array();

        //$stringHtml = $this->getStringHtmlResults($arrayResultsAbs, $entityName);

        return $this->render('MelanomamineFrontendBundle:Search:resultsOtherCancer.html.twig', array(
            'entityType' => $entityType,
            'whatToSearch' => $whatToSearch,
            'entityName' => $entityName,
            'arrayResultsAbs' => $arrayResultsAbs,
            'arrayResultsDoc' => $arrayResultsDoc,
            'resultSetAbstracts' => $data,
            'resultSetDocuments' => $resultSetDocuments,
            'entityName' => $entityName,
            'orderBy' => $orderBy,
            'hitsShowed' => $totalHits,
            'meanScore' => $meanScore,
            'medianScore' => $medianScore,
            'rangeScore' => $rangeScore,
            'totalTime' => $totalTime,
            'summaryTable' => $summaryTable,
            'filenameSummaryTable' => $filename,
            'arraySummaryTitles' => $arraySummaryTitles,

        ));
    }

    public function searchKnowledgeAction($concept1, $entityType1, $concept2, $entityType2){
        $message="Inside searchKnowledgeAction";

        //DIRECT ASSOCCIATIONS

        $isAmbiguousTerm1=$this->isAmbiguousTerm($concept1, $entityType1);
        $isAmbiguousTerm2=$this->isAmbiguousTerm($concept2, $entityType2);
        if((gettype($isAmbiguousTerm1)=="object" and $entityType1!="freeText" )or (gettype($isAmbiguousTerm2)=="object" and $entityType2!="freeText")){

            //We need to check if entityType1 and entityType2 are freeText because in this case isAmbiguousTerm will retrieve a $data to get results but should not be taken into account for disambiguation process.

            $message="disambiguation needed";
            return $this->render('MelanomamineFrontendBundle:Search:knowledgeDisambiguation.html.twig', array(
                'entityType' => "knowledge",
                'concept1' => $concept1,
                'entityType1' => $entityType1,
                'concept2' => $concept2,
                'entityType2' => $entityType2,
                'whatToSearch' => "knowledge",
                'isAmbiguousTerm1' => $isAmbiguousTerm1,
                'isAmbiguousTerm2' => $isAmbiguousTerm2
            ));
        }

        $arrayAbstracts1=$this->getDirectAssociations($concept1, $entityType1);
        $arrayAbstracts2=$this->getDirectAssociations($concept2, $entityType2);


        $arrayPmids1=array_keys($arrayAbstracts1);
        $arrayPmids2=array_keys($arrayAbstracts2);
        $arrayPmidsIntersection=array_intersect($arrayPmids1, $arrayPmids2);

        //So far we have Direct associations. All that we need is this three arrays: $arrayAbstracts1, $arrayAbstracts2, $arrayPmidsIntersection
        //INDIRECT ASSOCCIATIONS (Disambiguation process has been already runned)
        //We start from $arrayAbstracts1 and $arrayAbstracts2

        //To show content of first element of arrayAbstracts:  ldd($arrayAbstracts1[$arrayPmids1[0]]);

        //INDIRECT ASSOCCIATIONS
        $arrayIndirectAssociations=$this->getIndirectAssociations($arrayPmids1, $arrayAbstracts1, $arrayPmids2, $arrayAbstracts2);
        //ldd($arrayIndirectAssociations["arrayGenes1"]);

        /*
        $arrayGenes1=$arrayReturn["arrayGenes1"];
        $arrayGenes2=$arrayReturn["arrayGenes2"];
        $arrayCompounds1=$arrayReturn["arrayCompounds1"];
        $arrayCompounds2=$arrayReturn["arrayCompounds2"];
        $arrayIntersectionGenesSamePmids=$arrayIndirectAssociations["arrayIntersectionGenesSamePmids"];
        $arrayIntersectionGenesDifferentPmids=$arrayIndirectAssociations["arrayIntersectionGenesDifferentPmids"];
        $arrayGenesSinglets1=$arrayIndirectAssociations["arrayGenesSinglets1"];
        $arrayGenesSinglets2=$arrayIndirectAssociations["arrayGenesSinglets2"];
        $arrayIntersectionCompoundsSamePmids=$arrayIndirectAssociations["arrayIntersectionCompoundsSamePmids"];
        $arrayIntersectionCompoundsDifferentPmids=$arrayIndirectAssociations["arrayIntersectionCompundsDifferentPmids"];
        $arrayCompoundsSinglets1=$arrayIndirectAssociations["arrayCompoundsSinglets1"];
        $arrayCompoundsSinglets2=$arrayIndirectAssociations["arrayCompoundsSinglets2"];
        */
        return $this->render('MelanomamineFrontendBundle:Search:resultsKnowledge.html.twig', array(
            'entityType' => "knowledge",
            'whatToSearch' => "knowledge",
            'concept1' => $concept1,
            'entityType1' => $entityType1,
            'concept2' => $concept2,
            'entityType2' => $entityType2,
            'arrayAbstracts1' => $arrayAbstracts1,
            'arrayAbstracts2' => $arrayAbstracts2,
            'arrayPmids1' => $arrayPmids1,
            'arrayPmids2' => $arrayPmids2,
            'arrayPmidsIntersection' => $arrayPmidsIntersection,
            'arrayIndirectAssociations' => $arrayIndirectAssociations
        ));
    }
}