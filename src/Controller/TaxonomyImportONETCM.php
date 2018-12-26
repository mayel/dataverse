<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportONETCM extends Taxonomy // used to import data from https://www.onetcenter.org/database.html?p=2 - run it via command line: php bin/console import:onet --no-debug
{

    /**
    * @Route("/import/onet/{limit_depth}", name="timport")
    */
    public function import($limit_depth = 1) // better to run through command line
    {

        //		$this->skip_until_after_tags = ["Youth"=>"Baseball Socks"];

        $this->top_parent_tag_id = 4;


        $this->limit_depth = $this->max_existing_depth = $limit_depth;

        $db_name = 'onet23';
        R::addDatabase($db_name, 'mysql:host='.$this->conf->dbcreds['host'].';dbname='.$db_name, $this->conf->dbcreds['user'], $this->conf->dbcreds['pass']); // optional, if ONET db tables are in another DB

        R::selectDatabase($db_name);
        $this->entries = R::getAll('SELECT * FROM content_model_reference ORDER BY element_id');
        // var_dump($this->entries);

        R::selectDatabase('default'); // switch back DB

        $this->datas = array();

        $this->import_content_models();

        echo '<pre>';
        print_r($this->datas);
        exit();
    }

    public function import_content_models() // better to run through command line
    {
        //		$this->skip_until_after_tags = ["Youth"=>"Baseball Socks"];


        if (count($this->entries)) {
            foreach ($this->entries as $entry) {

              // print_r($entry);

                $tree_pieces = explode(".", $entry['element_id']);

                $tree_count = count($tree_pieces);

                $this->max_existing_depth = max($this->max_existing_depth, $tree_count);

                if ($tree_count == $this->limit_depth) {
                    // print_r($entry);

                    array_pop($tree_pieces); // parent id
                    $entry['parent_element'] = implode(".", $tree_pieces);

                    if ($entry['parent_element'] && $this->datas[$entry['parent_element']] && $this->datas[$entry['parent_element']]['tag_id']) {
                        $entry['parent_tag_id'] = $this->datas[$entry['parent_element']]['tag_id'];
                    }

                    $tag_id = $this->content_model_tag($entry);

                    $entry['tag_id'] = $tag_id;

                    $this->datas[$entry['element_id']] = $entry;
                }
            }
        }


        if ($this->max_existing_depth > $this->limit_depth) { // run on next level down
            $this->limit_depth++;
            return $this->import_content_models();
        }
    }


    public function content_model_tag($entry, $mock_run=false)
    {
        $tag = trim($entry['element_name']);

        if (!$tag) {
            exit("ERROR: Empty tag!");
        }

        if (isset($entry['element_id'])) {
            $m_set['Code']['ONET'] = $entry['element_id'];
        }
        if (isset($entry['description']) && $entry['description'] !=$tag) {
            $m_set['Description'][null] = trim($entry['description']);
        }

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
