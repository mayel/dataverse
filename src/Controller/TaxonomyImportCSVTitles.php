<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportCSVTitles extends Taxonomy // TEMPORARY: run it via command line: php bin/console import:csv --no-debug
{

    /**
    * @Route("/import/csv/{limit_depth}", name="timport")
    */
    public function import($limit_depth = 5) // better to run through command line
    {
        $fn = "/custom/titles.csv"; // input file name

        // $this->skip_until_after_tags = ["Auto Transmission Specialist"];

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

                    $entry['element_name'] = $key = trim(array_pop($parts)); // tag name is last col

                    if ($this->skip_until_after_tags) {
                        if (in_array($key, $this->skip_until_after_tags)) {
                            $this->skip_tag_found = true;
                            continue;
                        } elseif (!$this->skip_tag_found) {
                            continue;
                        }
                    }

                    $entry['parent_element_id'] = $parts[0];

                    // var_dump($entry, $parts);

                    $tag_id = $this->occupation_tag_add($entry);
                    $entry['tag_id'] = $tag_id;

                    $this->datas[] = $entry;
                }
            }
        }

        echo '<pre>';
        print_r($this->datas);
        exit();
    }


    public function occupation_tag_add($entry, $mock_run = false)
    {
        $m_set = [];
        $tag = trim($entry['element_name']);

        if (!$tag) {
            exit("ERROR: Empty tag!");
        }

        // if (isset($entry['element_id'])) {
        //     $m_set['Code']['SOC'] = $entry['element_id'];
        // }
        // if (isset($entry['description']) && $entry['description'] !=$tag) {
        //     $m_set['Description'][null] = trim($entry['description']);
        // }

        // echo "\n\n$tag\n";
        if (isset($entry['parent_element_id']) && $entry['parent_element_id']) {
            $parent_tag = $this->tag_by_meta("Code", "SOC", $entry['parent_element_id']);

            if (!$parent_tag) {
                exit("ERROR: Could not find parent tag!");
            }

            // print_r($parent);
            $parent = $parent_tag->id;
        }

        echo "\n\n$parent\t\t\t\t$tag\n";
        // print_r($m_set);

        if ($mock_run) { // for testing

            if (!isset($this->mock_id)) {
                $this->mock_id=1;
            } else {
                $this->mock_id++;
            }

            $tag_id = $this->mock_id;
        } else {
            unset($this->item); // needed to avoid overide

            $tag_id = $this->tag_add($tag, $parent, false, $m_set);

            if (!$tag_id) {
                exit("\nCould not create tag '$tag' !");
            }
        }

        echo "\nID: $tag_id\n";

        return $tag_id;
    }
}
