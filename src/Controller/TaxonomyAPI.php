<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

class TaxonomyAPI extends Taxonomy
{


    /**
    * @Route("/taxonomy/{taxonomy_id}/tag/{parent_id}", name="taxonomy_by_and_tag")
    */
    public function taxonomy_by_and_tag($taxonomy_id = false, $parent_id = 1)
    {
        return $this->taxonomy_output($parent_id, $taxonomy_id);
    }

    /**
    * @Route("/taxonomy/tag/{parent_id}", name="taxonomy_by_tag")
    */
    public function taxonomy_by_tag($parent_id = 1)
    {
        return $this->taxonomy_output($parent_id);
    }

    /**
    * @Route("/taxonomy/{taxonomy_id}", name="full_taxonomy_by_id")
    */
    public function full_taxonomy_by_id($taxonomy_id = false)
    {
        return $this->taxonomy_output(1, $taxonomy_id);
    }

    /**
    * @Route("/taxonomy/{taxonomy_id}/tag/{tag_id}/tags", name="search_tags_taxonomy_by_id_under_tag")
    */
    public function search_tags_taxonomy_by_id_under_tag($taxonomy_id = false, $tag_id=false)
    {
        return $this->search_tags_taxonomy_by_id($taxonomy_id, $tag_id);
    }

    /**
    * @Route("/taxonomy/meta/{type}/{detail}/{data}/tags", name="search_tags_by_meta_data")
    */
    public function search_tags_by_meta_data($type=null, $detail=null, $data=null)
    {
        header("Access-Control-Allow-Origin: *");
        return $this->json($this->tags_by_meta($type, $detail, $data));
    }

    /**
    * @Route("/taxonomy/meta/{type}/{detail}/tags", name="search_tags_by_meta")
    */
    public function search_tags_by_meta($type=null, $detail=null)
    {
        return $this->search_tags_by_meta_data($type, $detail, null);
    }


    /**
    * @Route("/taxonomy/{taxonomy_id}/tags", name="search_tags_taxonomy_by_id")
    */
    public function search_tags_taxonomy_by_id($taxonomy_id = false, $under_tag=false)
    {
        $term   = $_GET['q'] ? $_GET['q'] : $_GET['term'];
        $via   = $_GET['via'];

        if (!$separator) {
            $separator = $_REQUEST['separator'];
        }

        if (!$separator) {
            $separator = '≫';
        }

        $limit = 50;

        $this->tag_search_return = new class {};

        $this->tag_search_prepare($term, $limit, $page, $separator, $under_tag);

        header("Access-Control-Allow-Origin: *");
        return $this->json($this->tag_search_return);
    }

    public function tag_search_prepare($term, $limit, $page, $separator, $under_tag){

        $results = $this->search_tags($term, $limit, $page);

        foreach ($results as $r) {
            // var_dump($r, $under_tag);
            $r = (object) $r;

            $ancestors_str = $this->tag_name_with_ancestors($r->id, $separator, $under_tag);

            if ($ancestors_str) { // have breadcrumb
                $r->text = $ancestors_str;
                $this->tag_search_return->results[] = $r;
            } else { // is not a child of under_tag
                // $r->text = $r->label;
                // $this->tag_search_return->results[] = $r;
            }

        }

        if(count($results)==50 && count($this->tag_search_return->results)<45 && $page<10){ // too many were culled, search for some more
            $this->tag_search_prepare($term, $limit, $page+1, $separator, $under_tag);
        }

    }

    /**
    * @Route("/taxonomy/{taxonomy_id}/tag/{parent_tag}/new", name="taxonomy_tag_new")
    */
    public function taxonomy_tag_new($taxonomy_id, $parent_tag)
    {
        if ($taxonomy_id) {
            $this->taxonomy_id = $taxonomy_id;
        }

        return $this->tag_new($parent_tag);
    }

    /**
    * @Route("/taxonomy/tag/{parent_tag}/new", name="tag_add")
    */
    public function tag_new($parent_tag)
    {
        $tag_id = $this->tag_add($_REQUEST['label'], $parent_tag, $_REQUEST['grandparent'], $_REQUEST['meta']);

        if ($_REQUEST['format']=='redirect') {
            header("Location: /taxonomies?toast=Tag added!&tag_id=".$tag_id);
        } else {
            return $this->json($this->item);
        }
    }


    /**
    * @Route("/taxonomy/tag/{tag_id}/edit", name="api_tag_edit")
    */
    public function api_tag_edit($tag_id)
    {
        $e = $this->tag_edit($tag_id, $_REQUEST);

        if ($_REQUEST['format']=='redirect') {
            header("Location: /taxonomies?toast=Tag edited!&tag_id=".$tag_id);
        } else {
            return $this->json("Edited");
        }
    }

    /**
    * @Route("/taxonomy/tag/{tag_id}/delete", name="tag_delete")
    */
    public function tag_delete($tag_id)
    {
        $del = $this->tag_hide($tag_id);

        if ($_REQUEST['format']=='redirect') {
            header("Location: /taxonomies?toast=Tag deleted!&tag_id=".$tag_id);
        } else {
            return $this->json("Deleted");
        }
    }
}
