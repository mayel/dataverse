<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

class TaxonomyWikiLinkRelated extends Taxonomy
{


    public function import()
    {
        
        $wrels = R::findAll( 'wikirelation' , ' ORDER BY id ASC ' );

        // print_r($wrels);

        foreach($wrels as $wrel){
            $tag1 = ($wrel->tag);

            $tag2 = $this->tags_by_meta("Code", "HAWB", $wrel->wikid, true);

            if (!$tag2 || !$tag2->id) {
                exit("ERROR: Could not find tag $wrel->wikid !");
            }

            print_r($wrel);
        }

        return 'ok';
    }

    
}
