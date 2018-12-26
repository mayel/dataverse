<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportCSVSOC extends Taxonomy // TEMPORARY: run it via command line: php bin/console import:csv --no-debug
{

    /**
    * @Route("/import/csv/{limit_depth}", name="timport")
    */
    public function import($limit_depth = 5) // better to run through command line
    {
        $this->top_parent_tag_id = 60885;

        $fn = "/custom/SOC.csv"; // input file name

        //		$this->skip_until_after_tags = ["Youth"=>"Baseball Socks"];

        $this->tree_depth = 5;
        $this->limit_depth = $limit_depth;
        $this->max_cols = min($this->limit_depth, $this->tree_depth);

        $hasHeader = true; // input file has header, we will skip first line

        $this->datas = array();
        $this->prev_parents = array();

        if (($handle = fopen($this->base_path.$fn, "r")) !== false) {
            while (($parts = fgetcsv($handle, 0, ";")) !== false) {
                if ($hasHeader && !isset($header)) {
                    $header = $parts;
                } else {

                    // $minfo = [];
                    // $minfo = array_combine($header, $parts);

                    $entry['element_name'] = trim(array_pop($parts)); // tag name

                    $tag_f = array_filter($parts); // tag ID
                    $entry['element_id'] = trim(array_pop($tag_f)); // SOC tag ID

                    // var_dump($entry, $parts); //

                    if ($parts[0]) {
                        $entry['parent_tag_id'] = $this->top_parent_tag_id;
                    } // to use the top parent
                    elseif ($parts[1]) {
                        $entry['parent_tag_id'] = $this->prev_parents[0];
                    } elseif ($parts[2]) {
                        $entry['parent_tag_id'] = $this->prev_parents[1];
                    } elseif ($parts[3]) {
                        $entry['parent_tag_id'] = $this->prev_parents[2];
                    }

                    $tag_id = $this->occupation_tag($entry);
                    $entry['tag_id'] = $tag_id;

                    if ($parts[0]) {
                        $this->prev_parents[0] = $tag_id;
                    } elseif ($parts[1]) {
                        $this->prev_parents[1] = $tag_id;
                    } elseif ($parts[2]) {
                        $this->prev_parents[2] = $tag_id;
                    }

                    $this->datas[$entry['element_id']] = $entry;
                }
            }
        }

        echo '<pre>';
        print_r($this->datas);
        exit();
    }


    public function occupation_tag($entry, $mock_run=false)
    {
        $tag = trim($entry['element_name']);

        if (!$tag) {
            exit("ERROR: Empty tag!");
        }

        if (isset($entry['element_id'])) {
            $m_set['Code']['SOC'] = $entry['element_id'];
        }
        // if (isset($entry['description']) && $entry['description'] !=$tag) {
        //     $m_set['Description'][null] = trim($entry['description']);
        // }

        if (isset($entry['parent_tag_id']) && $entry['parent_tag_id']) {
            $parent = $entry['parent_tag_id'];
        } else {
            $parent = $this->top_parent_tag_id;
        }

        echo "\n\n$parent\t\t\t\t$tag\n";
        print_r($m_set);

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
