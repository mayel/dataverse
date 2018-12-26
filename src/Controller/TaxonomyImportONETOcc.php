<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportONETOcc extends Taxonomy // used to import data from https://www.onetcenter.org/database.html?p=2 - run it via command line: php bin/console import:onet --no-debug
{

    /**
    * @Route("/import/onet/{limit_depth}", name="timport")
    */
    public function import() // better to run through command line
    {
        $this->skip_until_after_tags = ["Administrative Services Managers"];

        // $this->import_table = "content_model_reference";
        $this->import_table = "occupation_data";

        $this->top_parent_tag_id = 68871;

        $db_name = 'onet23';
        R::addDatabase($db_name, 'mysql:host='.$this->conf->dbcreds['host'].';dbname='.$db_name, $this->conf->dbcreds['user'], $this->conf->dbcreds['pass']); // optional, if ONET db tables are in another DB

        R::selectDatabase($db_name);
        $this->entries = R::getAll('SELECT * FROM '.$this->import_table.' ORDER BY onetsoc_code');
        // var_dump($this->entries);

        R::selectDatabase('default'); // switch back DB

        $this->datas = array();
        $this->skip_tag_found = false;

        // $this->import_content_models();
        $this->import_occupations();

        echo '<pre>';
        print_r($this->datas);
        exit();
    }

    public function import_occupations() // better to run through command line
    {
        if (count($this->entries)) {
            foreach ($this->entries as $entry) {

              // print_r($entry);

                $key = trim($entry['title']);

                if ($this->skip_until_after_tags) {
                    if (in_array($key, $this->skip_until_after_tags)) {
                        $this->skip_tag_found = true;
                        continue;
                    } elseif (!$this->skip_tag_found) {
                        continue;
                    }
                }

                $tree_pieces = explode(".", $entry['onetsoc_code']);

                if ($tree_pieces[1] > 0) { // sub-SOC

                    $entry['is_sub'] = true;
                    $entry['element_id'] = $entry['onetsoc_code'];
                    $entry['parent_element'] = $tree_pieces[0];
                } else { // standard SOC

                    $entry['is_sub'] = false;
                    $entry['element_id'] = $tree_pieces[0];
                }

                // print_r($entry);

                $tag_id = $this->occupation_tag($entry);

                $entry['tag_id'] = $tag_id;

                $this->datas[$entry['element_id']] = $entry;
            }
        }
    }


    public function occupation_tag($entry, $mock_run = false)
    {
        $tag = trim($entry['title']);

        if (!$tag) {
            exit("ERROR: Empty tag!");
        }


        if (isset($entry['element_id'])) {
            $m_set['Code']['SOC'] = $entry['element_id'];
        }
        if (isset($entry['description']) && $entry['description'] !=$tag) {
            $m_set['Description'][''] = trim($entry['description']);
        }

        if ($entry['is_sub']) { // sub SOC - run after having done the other

            // return "SUB! ".$entry['element_id'];

            $tag_parent = $this->tags_by_meta("Code", "SOC", $entry['parent_element'], true);

            if ($tag_parent && is_object($tag_parent)) {
                $parent = $tag_parent->id;
            }
            //return "SUB!! ".$tag_parent->label ." / ID:". $tag_parent->id;
            else {
                return "LOSTF :".$entry['parent_element'];
            }
        } elseif (1==0) { // main tag - maybe dup

            $check_tag = $this->tags_by_meta("Code", "SOC", $entry['element_id'], true);
            // print_r($tag);
            if ($check_tag) {
                return "DUP!! ".$check_tag->label ." / ID:". $check_tag->id;
            } // dup

            else { // new

                $possible_parent_id = substr_replace((string) $entry['element_id'], '0', -1, 1);
                $tag_parent = $this->tags_by_meta("Code", "SOC", $possible_parent_id, true);

                if (!$tag_parent) {
                    $possible_parent_id = substr_replace((string) $entry['element_id'], '00', -2, 2);
                    $tag_parent = $this->tags_by_meta("Code", "SOC", $possible_parent_id, true);
                }

                if (!$tag_parent) {
                    $possible_parent_id = substr_replace((string) $entry['element_id'], '000', -3, 3);
                    $tag_parent = $this->tags_by_meta("Code", "SOC", $possible_parent_id, true);
                }

                if (!$tag_parent) {
                    $possible_parent_id = substr_replace((string) $entry['element_id'], '0000', -4, 4);
                    $tag_parent = $this->tags_by_meta("Code", "SOC", $possible_parent_id, true);
                }

                if ($tag_parent) {
                    $parent = $tag_parent->id;

                    $check_tag = $this->tag_by_name_with_parent_id($tag, $parent);
                    if ($check_tag) {
                        return "NEWDUP!! ".$check_tag->label ." / ID:". $check_tag->id;
                    } // dup
 
            // return "SORT!! ".$tag_parent->label ." / ID:".$parent;
                } else {
                    $parent = $this->top_parent_tag_id;
                }

                // return "NEW MESSY!! ".$parent;
            }
        }

        if (!isset($parent)) {
            return("ERROR: Could not find parent tag!");
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

            try {
                //PDO query execution goes here.

                $tag_id = $this->tag_add($tag, $parent, false, $m_set);
            } catch (Exception $e) {
                return "BARFED on insert ";
            }



            if (!$tag_id) {
                exit("\nCould not create tag '$tag' !");
            }
        }

        // echo "\nID: $tag_id\n";

        return $tag_id;
    }
}
