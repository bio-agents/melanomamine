<?php

namespace Melanomamine\FrontendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    public function removeOldFiles() {
        $message="inside removeOldFiles";
        $path = $this->get('kernel')->getRootDir(). "/../web/files/summaryTables/*";
        $files = glob($path);

        $now   = time();
        //$timeOld = 60; //(60 secs)
        $timeOld = 60 * 60 * 24 * 2; //(2 days)


        foreach ($files as $file)
            if (is_file($file))
                if ($now - filemtime($file) >= $timeOld) // 2 days
                    unlink($file);
    }

    public function downloadSummaryTableAction($filenameSummaryTable){
        //ld($filenameSummaryTable);

        //Clean directory from old files
        $this->removeOldFiles();

        $request = $this->get('request');
        $path = $this->get('kernel')->getRootDir(). "/../web/files/summaryTables/";
        $filepath=$path.$filenameSummaryTable;
        //ldd($filepath);
        $content = file_get_contents($filepath);

        $response = new Response();

        //set headers
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.$filenameSummaryTable);

        $response->setContent($content);
        return $response;




    }
}
