<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportCSVDesc extends Taxonomy // TEMPORARY: run it via command line: php bin/console import:csv --no-debug
{

    /**
    * @Route("/import/csv/{limit_depth}", name="timport")
    */
    public function import($limit_depth = 5) // better to run through command line
    {
        $fn = "/custom/descr.csv"; // input file name

        $this->skip_until_after_tags = ["Chief Executives"];

        $this->tree_depth = 3;
        $this->limit_depth = $limit_depth;
        $this->max_cols = min($this->limit_depth, $this->tree_depth);

        $hasHeader = true; // input file has header, we will skip first line

        $this->datas = array();
        $this->prev_parents = array();
        $this->skip_tag_found = false;

        if (($handle = fopen($this->base_path.$fn, "r")) !== false) {
            while (($parts = fgetcsv($handle, 0, ";")) !== false) {
                if ($hasHeader && !isset($header)) {
                    $header = $parts;
                } else {

                    // $minfo = [];
                    // $minfo = array_combine($header, $parts);

                    $key = trim($parts[2]); // tag name
                    $entry['element_id'] = trim($parts[1]); // SOC tag ID
                    $entry['description'] = trim(array_pop($parts)); // descr name is last col

                    if ($this->skip_until_after_tags) {
                        if (in_array($key, $this->skip_until_after_tags)) {
                            $this->skip_tag_found = true;
                            continue;
                        } elseif (!$this->skip_tag_found) {
                            continue;
                        }
                    }

                    // var_dump($entry, $parts);

                    $tag_id = $this->occupation_descr_add($entry);

                    // $entry['tag_id'] = $tag_id;

                    $this->datas[] = $entry;
                }
            }
        }

        // echo '<pre>'; print_r($this->datas);
        exit();
    }


    public function occupation_descr_add($entry, $mock_run = false)
    {
        $m_set = [];

        // if (isset($entry['element_id'])) {
        //     $m_set['Code']['SOC'] = $entry['element_id'];
        // }
        if (isset($entry['description']) && $entry['description']) {
            $desc = ($entry['description']);
        } else {
            return;
        }

        // echo "\n\n$tag\n";
        if (isset($entry['element_id']) && $entry['element_id']) {
            $tag = $this->tags_by_meta("Code", "SOC", $entry['element_id'], true);

            if (!$tag || !$tag->id) {
                exit("ERROR: Could not find tag!");
            }

            // var_dump($tag, $desc);

            echo "\n\nTID: ".$tag->id."\t\t\tDESCR:\t$desc\n";

            $meta = $this->meta_add_to_tag($tag, "Description", '', $desc);
            // exit();
        }



        // return $meta;
    }
}
