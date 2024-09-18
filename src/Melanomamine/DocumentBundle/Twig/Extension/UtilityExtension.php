<?php

namespace Melanomamine\DocumentBundle\Twig\Extension;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Twig_Extension;
use Twig_Filter_Method;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;


class UtilityExtension extends \Twig_Extension
{
    protected $doctrine;
    protected $generator;
    protected $container;
    protected $searchChemSpider;

    public function __construct(RegistryInterface $doctrine, UrlGeneratorInterface $generator, ContainerInterface $container = null)
    {
        $this->doctrine = $doctrine;
        $this->generator = $generator;
        $this->container = $container;
    }

    public function getFilters()
    {
        return array(
            'retrieveHighlighted' => new \Twig_Filter_Method($this, 'retrieveHighlighted'),
            'generateSummaryTitleString' => new \Twig_Filter_Method($this, 'generateSummaryTitleString'),
            'colorCodingScoreFilter' => new \Twig_Filter_Method($this, 'colorCodingScoreFilter'),
            'generatePath' => new \Twig_Filter_Method($this, 'generatePath'),
            'getOrderBy' => new \Twig_Filter_Method($this, 'getOrderBy'),
        );
    }



    public function filterTitleText($source, $filter){
        $message="Inside filterTitleText";
        //ld($filter);
        $arrayIndexes=["chemicals2", "diseases3", "genes3", "mutatedProteins4", "snps", "species2"];

        /*
            For highlighting we create an array of dictionaries:  [{"start":4, "end": 20, "typeOf": "genes"},{"start":23, "end": 50, "typeOf": "diseases3"},{"start":56, "end": 80, "typeOf": "genes"},{"start":93, "end": 120, "typeOf": "mutatedProteins4"}...]
            We short it taking into account the "start" field
            We cut the string in parts and add span tags with colors. Then we concatenate the resulting strings and split the Title

        */
        $usedPositions=[];//To track positions already highlighted and avoid double-highlight
        if($filter=="title"){
            //Filter source to get only source involved in title positions
            $titleText=$source["title"];
            $titleLength=strlen($titleText);
            $arrayDictionaries=[];
            //ld($source);
            foreach($arrayIndexes as $index){
                //ld($index);
                if (array_key_exists($index, $source)){
                    $typeOf=$index;
                    $arrayData=$source[$index];//At $arrayData we have iteratively an array of chemicals, an array of diseases3... etc
                    //ld($arrayData);
                    foreach($arrayData as $data){
                        //ld($data);
                        $dictionaryTmp=[];
                        $dictionaryTmp["typeOf"]=$typeOf;

                        if($typeOf == "mutatedProteins4"){

                            $mutation=$data["mention"];
                            $start=$data["startMutation"];
                            $end=$data["endMutation"];
                            $mutationClass=$data["mutationClass"];
                            $sequenceType=$data["sequenceType"];
                            $sequenceClass=$data["sequenceClass"];
                            $wildType_aa=$data["wildType_aa"];
                            $sequencePosition=$data["sequencePosition"];
                            $mutant_aa=$data["mutant_aa"];
                            $frameshiftPosition=$data["frameshiftPosition"];
                            $mutationValidated=$data["mutationValidated"];
                            $ncbiGenId=$data["ncbiGenId"];
                            $uniprotAccession=$data["uniprotAccession"];
                            $geneMention=$data["geneMention"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $taxonomyScore=$data["taxonomyScore"];
                            $pmidCheck=$data["pmidCheck"];
                            $signalPeptideLength=$data["signalPeptideLength"];
                            $proteinSequence=$data["proteinSequence"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mutationClass"]=$mutationClass;
                            $dictionaryTmp["sequenceType"]=$sequenceType;
                            $dictionaryTmp["sequenceClass"]=$sequenceClass;
                            $dictionaryTmp["wildType_aa"]=$wildType_aa;
                            $dictionaryTmp["sequencePosition"]=$sequencePosition;
                            $dictionaryTmp["mutant_aa"]=$mutant_aa;
                            $dictionaryTmp["frameshiftPosition"]=$frameshiftPosition;
                            $dictionaryTmp["mutationValidated"]=$mutationValidated;
                            $dictionaryTmp["ncbiGenId"]=$ncbiGenId;
                            $dictionaryTmp["uniprotAccession"]=$uniprotAccession;
                            $dictionaryTmp["geneMention"]=$geneMention;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;
                            $dictionaryTmp["taxonomyScore"]=$taxonomyScore;
                            $dictionaryTmp["pmidCheck"]=$pmidCheck;
                            $dictionaryTmp["signalPeptideLength"]=$signalPeptideLength;
                            $dictionaryTmp["proteinSequence"]=$proteinSequence;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;

                        }elseif($typeOf=="diseases3"){

                            //Addded to correct offset error for starting position for diseases in database
                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $ontology=$data["ontology"];
                            $ontologyId=$data["ontologyId"];
                            $dictionaryTmp["start"]=$start-1;//Addded to correct offset error for starting position for diseases in database
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["ontology"]=$ontology;
                            $dictionaryTmp["ontologyId"]=$ontologyId;

                        }elseif($typeOf=="genes3"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $ncbiGeneId=$data["ontology"];
                            $ontologyId=$data["ontologyId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["ncbiGeneId"]=$ncbiGeneId;
                            $dictionaryTmp["ontologyId"]=$ontologyId;

                        }elseif($typeOf=="species2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mention=$data["mention"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mention"]=$mention;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;

                        }elseif($typeOf == "mutations2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mutationClass=$data["mutationClass"];
                            $position=$data["position"];
                            $wildType=$data["wildType"];
                            $sequenceClass=$data["sequenceClass"];
                            $sequenceType=$data["sequenceType"];
                            $mutant=$data["mutant"];
                            $frameshiftPosition=$data["frameshiftPosition"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mutationClass"]=$mutationClass;
                            $dictionaryTmp["position"]=$position;
                            $dictionaryTmp["wildType"]=$wildType;
                            $dictionaryTmp["sequenceClass"]=$sequenceClass;
                            $dictionaryTmp["sequenceType"]=$sequenceType;
                            $dictionaryTmp["mutant"]=$mutant;
                            $dictionaryTmp["frameshiftPosition"]=$frameshiftPosition;

                        }elseif($typeOf == "chemicals2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mention=$data["mention"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mention"]=$mention;

                        }else{

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                        }

                        //Now we check if those positions are already highlighted or not.
                        //$message="start: $start - end: $end";
                        //ld($message);
                        //ld($usedPositions);
                        if( !in_array($start, $usedPositions) and !in_array($end, $usedPositions)){
                            //$message="start o end no se encuentran en el array usedPositions. Añadimos todo el rango";
                            //ld($message);
                            $dictionaryTmp["mention"]=$data["mention"];
                            $tmpUsedPositions=range($start,$end);
                            $usedPositions = array_merge($usedPositions, $tmpUsedPositions);
                            //ld($usedPositions);
                            if($start <= $titleLength){
                                array_push($arrayDictionaries, $dictionaryTmp);
                                //ld($arrayDictionaries);
                            }
                        }else{
                            $message="start o end se encuentran en el array usedPositions";
                            //ld($message);
                        }

                    }
                }
            }
            usort($arrayDictionaries,function($item1, $item2){
                    if ($item1['start'] == $item2['start']) return 0;
                    return ($item1['start'] > $item2['start']) ? 1 : -1;
                }
            );
        }elseif($filter=="text"){
            //Filter source to get only source involved in title positions
            $titleText=$source["text"];

            $offset=strlen($source["title"]);
            //ld($offset);
            $arrayDictionaries=[];
            //ld($source);
            foreach($arrayIndexes as $index){
                //ld($index);
                if (array_key_exists($index, $source)){
                    $typeOf=$index;
                    $arrayData=$source[$index];//At $arrayData we have iteratively an array of chemicals, an array of diseases3... etc
                    //ld($arrayData);
                    foreach($arrayData as $data){
                        //ld($data);
                        $dictionaryTmp=[];
                        $dictionaryTmp["typeOf"]=$typeOf;
                        if($typeOf == "mutatedProteins4"){

                            $mutation=$data["mention"];
                            $start=$data["startMutation"];
                            $end=$data["endMutation"];
                            $mutationClass=$data["mutationClass"];
                            $sequenceType=$data["sequenceType"];
                            $sequenceClass=$data["sequenceClass"];
                            $wildType_aa=$data["wildType_aa"];
                            $sequencePosition=$data["sequencePosition"];
                            $mutant_aa=$data["mutant_aa"];
                            $frameshiftPosition=$data["frameshiftPosition"];
                            $mutationValidated=$data["mutationValidated"];
                            $ncbiGenId=$data["ncbiGenId"];
                            $uniprotAccession=$data["uniprotAccession"];
                            $geneMention=$data["geneMention"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $taxonomyScore=$data["taxonomyScore"];
                            $pmidCheck=$data["pmidCheck"];
                            $signalPeptideLength=$data["signalPeptideLength"];
                            $proteinSequence=$data["proteinSequence"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mutationClass"]=$mutationClass;
                            $dictionaryTmp["sequenceType"]=$sequenceType;
                            $dictionaryTmp["sequenceClass"]=$sequenceClass;
                            $dictionaryTmp["wildType_aa"]=$wildType_aa;
                            $dictionaryTmp["sequencePosition"]=$sequencePosition;
                            $dictionaryTmp["mutant_aa"]=$mutant_aa;
                            $dictionaryTmp["frameshiftPosition"]=$frameshiftPosition;
                            $dictionaryTmp["mutationValidated"]=$mutationValidated;
                            $dictionaryTmp["ncbiGenId"]=$ncbiGenId;
                            $dictionaryTmp["uniprotAccession"]=$uniprotAccession;
                            $dictionaryTmp["geneMention"]=$geneMention;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;
                            $dictionaryTmp["taxonomyScore"]=$taxonomyScore;
                            $dictionaryTmp["pmidCheck"]=$pmidCheck;
                            $dictionaryTmp["signalPeptideLength"]=$signalPeptideLength;
                            $dictionaryTmp["proteinSequence"]=$proteinSequence;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;

                        }elseif($typeOf=="diseases3"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $ontology=$data["ontology"];
                            $ontologyId=$data["ontologyId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["ontology"]=$ontology;
                            $dictionaryTmp["ontologyId"]=$ontologyId;

                        }elseif($typeOf=="genes3"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mention=$data["mention"];
                            $ncbiGeneId=$data["ontology"];
                            $ontologyId=$data["ontologyId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mention"]=$mention;
                            $dictionaryTmp["ncbiGeneId"]=$ncbiGeneId;
                            $dictionaryTmp["ontologyId"]=$ontologyId;

                        }elseif($typeOf=="species2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mention=$data["mention"];
                            $ncbiTaxId=$data["ncbiTaxId"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mention"]=$mention;
                            $dictionaryTmp["ncbiTaxId"]=$ncbiTaxId;

                        }elseif($typeOf == "mutations2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mutationClass=$data["mutationClass"];
                            $position=$data["position"];
                            $wildType=$data["wildType"];
                            $sequenceClass=$data["sequenceClass"];
                            $sequenceType=$data["sequenceType"];
                            $mutant=$data["mutant"];
                            $frameshiftPosition=$data["frameshiftPosition"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mutationClass"]=$mutationClass;
                            $dictionaryTmp["position"]=$position;
                            $dictionaryTmp["wildType"]=$wildType;
                            $dictionaryTmp["sequenceClass"]=$sequenceClass;
                            $dictionaryTmp["sequenceType"]=$sequenceType;
                            $dictionaryTmp["$mutant"]=$$mutant;
                            $dictionaryTmp["frameshiftPosition"]=$frameshiftPosition;

                        }elseif($typeOf == "chemicals2"){

                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $mention=$data["mention"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                            $dictionaryTmp["mention"]=$mention;

                        }else{
                            $start=$data["startMention"];
                            $end=$data["endMention"];
                            $dictionaryTmp["start"]=$start;
                            $dictionaryTmp["end"]=$end;
                        }

                        //Now we check if those positions are already highlighted or not.
                        //$message="start: $start - end: $end";
                        //ld($message);
                        //ld($usedPositions);
                        if( !in_array($start, $usedPositions) and !in_array($end, $usedPositions)){
                            //$message="start o end no se encuentran en el array usedPositions. Añadimos todo el rango";
                            //ld($message);
                            $dictionaryTmp["mention"]=$data["mention"];
                            $tmpUsedPositions=range($start,$end);
                            $usedPositions = array_merge($usedPositions, $tmpUsedPositions);
                            //ld($usedPositions);
                            if($start >= $offset){
                                array_push($arrayDictionaries, $dictionaryTmp);
                            }
                        }else{
                            $message="start o end se encuentran en el array usedPositions";
                            //ld($message);
                        }
                    }
                }
            }
            usort($arrayDictionaries,function($item1, $item2){
                    if ($item1['start'] == $item2['start']) return 0;
                    return ($item1['start'] > $item2['start']) ? 1 : -1;
                }
            );
        }
        return ($arrayDictionaries);
    }

    public function retrieveHighlighted($titleOrText, $source, $entityName, $startOffset, $filter, $tooltipCounter){
        $message="Inside retrieveHighlighted service";
        $mouseoverDivs="";
        $offset=0; //To handle the offset of the text's starting point
        //$startOffset needs to be substracted to compensate the title positions
        //ld($source);

        if($filter=="title"){
            $source=$this->filterTitleText($source, "title");
            //ld($startOffset);
            //ld($source);
        }elseif($filter=="text"){
            $source=$this->filterTitleText($source, "text");
            //ld($titleOrText);
            //ld($startOffset);
            //ld($source);
        }
        $arrayDictionaries=$source;
        //ld($arrayDictionaries);
        //Now we generate the new arrayString with the added highlights:
        //We have to iterate over the arrayDictionaries and mark $titleTextDefinitive
        $counter=1;
        foreach($arrayDictionaries as $dictionary){
            $tooltipCounter=$tooltipCounter+1;
            $tooltipCounterLength=strlen($tooltipCounter);//To add this to the addToOffset.
            $start=$dictionary["start"];
            //ld($start);
            $end=$dictionary["end"];
            $typeOf=$dictionary["typeOf"];
            //The string_to_insert will be different depending on the typeOf. Therefore:
            switch ($typeOf){
                case "chemicals2":
                    $str_to_insert="<span class='chemicals_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=34+22+$tooltipCounterLength;

                    $chemical=$dictionary["mention"];
                    //$chemSpider=$this->container->get('melanomamine.searchChemSpider');
                    //$chemSpiderId=$chemSpider->searchChemspider($chemical);
                    //$mouseoverSummary="<strong>Chemical: </strong><a href='http://www.chemspider.com/Chemical-Structure.$chemSpiderId.html' target='_blank'>$chemical</a><br/>";
                    $mouseoverSummary="<strong>Chemical: </strong><a href='http://www.chemspider.com/Search.aspx?q=$chemical'>$chemical</a><br/>";

                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;

                case "diseases3":
                    $str_to_insert="<span class='diseases_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=33+22+$tooltipCounterLength;

                    $disease=$dictionary["mention"];
                    $ontology=$dictionary["ontology"];
                    $ontologyId=$dictionary["ontologyId"];
                    $mouseoverSummary="<strong>Disease: </strong>$disease<br/><strong>Ontology: </strong>$ontology<br/><strong>Ontology Id: </strong>";

                    if($ontology=="MESH"){
                        $mouseoverSummary.="<a href='http://www.nlm.nih.gov/cgi/mesh/2014/MB_cgi?field=uid&term=$ontologyId' target='_blank'>$ontologyId</a><br/>";
                    }elseif($ontology=="OMIM"){
                        $mouseoverSummary.="<a href='http://www.omim.org/entry/$ontologyId' target='_blank'>$ontologyId</a><br/>";
                    }
                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;

                case "genes3":
                    $str_to_insert="<span class='genes_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=30+22+$tooltipCounterLength;

                    $geneName=$dictionary["mention"];
                    $ncbiGeneId=$dictionary["ncbiGeneId"];
                    $ncbiTaxId=$dictionary["ontologyId"];
                    $mouseoverSummary="<strong>Gene Name: </strong><a href='http://www.ncbi.nlm.nih.gov/gene?term=$ncbiGeneId'  target='_blank'>$geneName</a><br/><strong>NCBI Gene Id: </strong><a href='http://www.ncbi.nlm.nih.gov/gene/$ncbiGeneId'  target='_blank'>$ncbiGeneId</a><br/><strong>NCBI Taxon Id: </strong><a href='https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=$ncbiTaxId' target='_blank'>$ncbiTaxId</a><br/>";
                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;

                case "mutatedProteins4":
                    $str_to_insert="<span class='mutatedProteins_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=40+22+$tooltipCounterLength;

                    $mutation=$dictionary["mention"];
                    $mutationClass=$dictionary["mutationClass"];
                    $sequenceType=$dictionary["sequenceType"];
                    $sequenceClass=$dictionary["sequenceClass"];
                    $wildType_aa=$dictionary["wildType_aa"];
                    $sequencePosition=$dictionary["sequencePosition"];
                    $mutant_aa=$dictionary["mutant_aa"];
                    $frameshiftPosition=$dictionary["frameshiftPosition"];
                    $mutationValidated=$dictionary["mutationValidated"];
                    $ncbiGenId=$dictionary["ncbiGenId"];
                    $uniprotAccession=$dictionary["uniprotAccession"];
                    $geneMention=$dictionary["geneMention"];
                    $ncbiTaxId=$dictionary["ncbiTaxId"];
                    $taxonomyScore=$dictionary["taxonomyScore"];
                    $pmidCheck=$dictionary["pmidCheck"];
                    $signalPeptideLength=$dictionary["signalPeptideLength"];
                    $proteinSequence=$dictionary["proteinSequence"];

                    $mouseoverSummary="<strong>Mutation: </strong>$mutation<br/><strong>Mutation Class: </strong>$mutationClass<br/><strong>Sequence Type: </strong>$sequenceType<br/><strong>Sequence Class: </strong>$sequenceClass<br/><strong>WildType_aa: </strong>$wildType_aa<br/><strong>Sequence Position: </strong>$sequencePosition<br/><strong>Mutant_aa: </strong>$mutant_aa<br/><strong>Frameshift Position: </strong>$frameshiftPosition<br/><strong>Mutation Validated: </strong>$mutationValidated<br/><strong>NCBI GeneId: </strong>$ncbiGenId<br/><strong>Uniprot Accession: </strong>$uniprotAccession<br/><strong>Gene Mention: </strong>$geneMention<br/><strong>NCBI TaxId: </strong>$ncbiTaxId<br/><strong>Taxonomy Score: </strong>$taxonomyScore<br/><strong>PMID Check: </strong>$pmidCheck<br/><strong>Signal Peptide Length: </strong>$signalPeptideLength<br/><strong>Protein Sequence: </strong>$proteinSequence<br/>
                    ";
                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;

                case "snps":
                    $str_to_insert="<span class='snps_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=29+22+$tooltipCounterLength;
                    $mutation=$dictionary["mention"];
                    $typeOf=$dictionary["typeOf"];

                    $mouseoverSummary="<strong>Mutation: </strong>$mutation<br/><strong>TypeOf: </strong>$typeOf<br/>
                    ";
                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;
                case "species2":
                    $str_to_insert="<span class='species_highlight' data-tooltip='sticky$tooltipCounter'>";
                    $addToOffset=32+22+$tooltipCounterLength;

                    $specie=$dictionary["mention"];
                    //$ncbiTaxId=$dictionary["ncbiTaxId"];
                    $mouseoverSummary="<strong>Specie: </strong>$specie<br/>";//<strong>NCBI Tax Id: </strong>$ncbiTaxId<br/>
                    $mouseoverDivs=$mouseoverDivs."<div id=\"sticky$tooltipCounter\"  class=\"atip\">$mouseoverSummary</div>";
                    break;



            }
            //ld($str_to_insert);
            if($filter=="title"){
                //Change at start position
                $position=$start-$startOffset+$offset;
                $titleOrText = substr_replace($titleOrText, $str_to_insert, $position, 0);
                $offset=$offset+$addToOffset;

                //Change at end position
                $str_to_insert="</span>";
                $position=$end-$startOffset +$offset;
                $titleOrText = substr_replace($titleOrText, $str_to_insert, $position, 0);
                $offset=$offset+7;
            }elseif($filter=="text"){
                //Change at start position
                $position=$start-$startOffset+$offset;
                $titleOrText = substr_replace($titleOrText, $str_to_insert, $position, 0);
                $offset=$offset+$addToOffset;

                //Change at end position
                $str_to_insert="</span>";
                $position=$end-$startOffset +$offset;
                $titleOrText = substr_replace($titleOrText, $str_to_insert, $position, 0);
                $offset=$offset+7;
            }
            //ld($titleOrText);
        }
        //ld($titleOrText);
        //Underline entityName
        $titleOrText=str_ireplace($entityName, "<span class='underline'>".$entityName."</span>", $titleOrText);
        $arrayReturn=array();
        $arrayReturn[0]=$titleOrText;
        $arrayReturn[1]=$mouseoverDivs;
        $arrayReturn[2]=$tooltipCounter;

        //return ($titleOrText);
        return ($arrayReturn);


    }

    public function generateSummaryTitleString($arraySummaryTitle)
    {
        $message = "Inside generateSummaryTitleString";
        //$stringSummaryTitle="<div class='summaryTitle'>Entity Summary Table: <table >";
        $stringSummaryTitle="<hr/><div class='summaryTitle'><small>";
        foreach ($arraySummaryTitle as $typeMention=>$arrayMentions){

            //$stringSummaryTitle.= "<th colspan='2'>$typeMention</th><tr><td><strong>Mention</strong></td><td><strong>#</strong></td></tr>";
            switch ($typeMention){
                case "genes":
                    $stringSummaryTitle.= "<br/><span class='typeMention genes_highlight'>$typeMention</span>: ";
                    break;
                case "mutations":
                    $stringSummaryTitle.= "<br/><span class='typeMention mutations_highlight'>$typeMention</span>: ";
                    break;
                case "chemicals":
                    $stringSummaryTitle.= "<br/><span class='typeMention chemicals_highlight'>$typeMention</span>: ";
                    break;
                case "diseases":
                    $stringSummaryTitle.= "<br/><span class='typeMention diseases_highlight'>$typeMention</span>: ";
                    break;
                case "mutatedProteins":
                    $stringSummaryTitle.= "<br/><span class='typeMention mutatedProteins_highlight'>$typeMention</span>: ";
                    break;
                case "species":
                    $stringSummaryTitle.= "<br/><span class='typeMention species_highlight'>$typeMention</span>: ";
                    break;
            }

            arsort($arrayMentions);
            foreach($arrayMentions as $mention=>$totalMentions){
                $stringSummaryTitle.="$mention [$totalMentions], ";
            }
        }
        //Get rid of last ,&nbsp;
        $stringSummaryTitle = substr($stringSummaryTitle, 0, -2);
        $stringSummaryTitle.="</small></div>";
        return $stringSummaryTitle;
    }

    public function colorCodingScoreFilter($score)
    {
        if ($score==null){
            $score="-";
            return $score;
        }
        $score=(float)$score;
        switch ($score) {
            case (int)$score === 0:
                    $score ="<mark class=''>$score</mark>";
                    break;
            case $score>5:
                    $score ="<mark class='score-green-5'>$score</mark>";
                    break;
            case $score>4:
                    $score ="<mark class='score-green-4'>$score</mark>";
                    break;
            case $score>3:
                    $score ="<mark class='score-green-3'>$score</mark>";
                    break;
            case $score>2:
                    $score ="<mark class='score-green-2'>$score</mark>";
                    break;
            case $score>1:
                    $score ="<mark class='score-green-1'>$score</mark>";
                    break;
            case $score>0:
                    $score ="<mark class='score-green-0'>$score</mark>";
                    break;
            case $score < -5:
                    $score ="<mark class='score-red-5'>$score</mark>";
                    break;
            case $score < -4:
                    $score ="<mark class='score-red-4'>$score</mark>";
                    break;
            case $score < -3:
                    $score ="<mark class='score-red-3'>$score</mark>";
                    break;
            case $score < -2:
                    $score ="<mark class='score-red-2'>$score</mark>";
                    break;
            case $score < -1:
                    $score ="<mark class='score-red-1'>$score</mark>";
                    break;
            case $score < 0:
                    $score ="<mark class='score-red-0'>$score</mark>";
                    break;


        }
        return ($score);
    }

    public function generatePath($currentPath,$orderBy, $environment)
    {
        $message = "Inside generatePath";
        $arrayCurrentPath=explode("/", $currentPath);
        if($environment=="dev"){
            $arrayCurrentPath[6]=$orderBy;
        }elseif($environment=="prod"){
            $arrayCurrentPath[5]=$orderBy;
        }

        $path=implode("/", $arrayCurrentPath);
        return $path;
    }

    public function getOrderBy($orderBy)
    {

        switch ($orderBy){
            case "bladder_score":
                $orderBy = "bladder";
                break;
            case "breast_score":
                $orderBy = "breast";
                break;
            case "glioblastoma_score":
                $orderBy = "glioblastome";
                break;
            case "hcc_score":
                $orderBy = "hcc";
                break;
            case  "nsclc_score":
                $orderBy = "nsclc";
                break;
            case "melanoma_score_2":
                $orderBy = "melanome";
                break;
            case  "pancreas_score":
                $orderBy = "pancreas";
                break;
            case  "prostate_score":
                $orderBy = "prostate";
                break;
        }

        return $orderBy;
    }


    public function getName()
    {
        return 'utility_extension';
    }
}


?>