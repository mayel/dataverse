<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

use \Wikibase\DataModel\Services\Lookup\EntityRetrievingTermLookup;

set_time_limit(0);
ini_set("memory_limit", "2048M");
//ignore_user_abort(true);

class TaxonomyImportWikibase extends Taxonomy
// TEMPORARY: used to import taxonomy data from a wikibase site like wikidata.org
// it is recommend to run via command line: php bin/console import:wiki --no-debug
{

    /**
    * @Route("/import/wikibase/{item_id}", name="wikimport")
    */
    public function import($item_id = 'Q636') // better to run through command line
    {
      global $bv;

      // $item_ids = ['Q1694','Q1696','Q1695','Q1700','Q1701','Q1699', 'Q1698', 'Q1697']; // all


      //		$this->skip_until_after_tags = ["Youth"=>"Baseball Socks"];

      $api_url = ($_GET && $_GET['api_url'] ? $_GET['api_url'] : 'https://wiki.haha.academy/api.php');


      // Wikibase API Setup

      $api = new \Mediawiki\Api\MediawikiApi($api_url);

      // $api->login(new ApiUser('username', 'password')); // if authentication is needed

      // Create our Factory, All services should be used through this!
      // You will need to add more or different datavalues here.
      // In the future Wikidata / Wikibase defaults will be provided in seperate a library.
      $dataValueClasses = array(
          'unknown' => '\DataValues\UnknownValue',
          'string' => '\DataValues\StringValue',
          'boolean' => '\DataValues\BooleanValue',
          'number' => '\DataValues\NumberValue',
          'globecoordinate' => '\DataValues\Geo\Values\GlobeCoordinateValue',
          'monolingualtext' => '\DataValues\MonolingualTextValue',
          'multilingualtext' => '\DataValues\MultilingualTextValue',
          'quantity' => '\DataValues\QuantityValue',
          'time' => '\DataValues\TimeValue',
          'wikibase-entityid' => '\Wikibase\DataModel\Entity\EntityIdValue',
      );
      $bv->wbFactory = new \Wikibase\Api\WikibaseFactory(
          $api,
          new \DataValues\Deserializers\DataValueDeserializer($dataValueClasses),
          new \DataValues\Serializers\DataValueSerializer()
      );


      //  lookup an item and all it's children and relationships:

      $bv->ids_already_followed = [];

      $out = $this->wiki_item_get($item_id);


      echo json_encode($out); // display for debugging

      // $this->import_iterate($out); // add the data to our taxonomy DB


      exit();
  }

  function wiki_item_get($item_id, $recurse=1, $link_property=false){ // set $recurse=false to only get the element (not the statements)
    global $bv;

    try {

    $bv->itemLookup = $bv->wbFactory->newItemLookup();

    $itemId = new \Wikibase\DataModel\Entity\ItemId($item_id); // eg: Q1694

    // $entityRedirectLookup = $bv->wbFactory->newEntityRedirectLookup();

    $item = $bv->itemLookup->getItemForId($itemId);


    // print_r($item);

    if ($item) {
        $item_out = new class {};
        $item_out->id = $item_id;

        // echo " itemId: $itemId ";

        $bv->termLookup = $bv->wbFactory->newTermLookup();
        // $bv->termLookup = getEntityRetrievingTermLookup

        $enLabel = $bv->termLookup->getLabel($itemId, 'en');
        if($enLabel){
          $this->import_log($itemId .': '. $enLabel, $recurse);
          $item_out->name = $enLabel;
        } else echo " ERROR!! NO LABEL ";



        $enDescription = $bv->termLookup->getDescription($itemId, 'en');
        if ($enDescription) {
            $this->import_log('- '.$enDescription, $recurse);
            $item_out->description = $enDescription;
        }



        // print_r($item);
        // $redirectSources = $entityRedirectLookup->getRedirectIds($itemId);

        if ($link_property) {
            $item_out->link_property = $link_property;
        }

        if ($recurse && !in_array($item_id, $bv->ids_already_followed)) { // choose how many levels down to go

            $bv->ids_already_followed[] = $item_id;

            $statementList = $item->getStatements();

            foreach ($statementList as $statement) {
                // print_r($statement);
                $snack= $statement->getMainSnak();
                // print_r($snack);
                $property= $snack->getPropertyId();
                $this->import_log($property, $recurse);

                if ($property !='P11') { // ignore "Subtheme of"
                    $sub_item= $snack->getDataValue();
                    // print_r($sub_item);

                    $sub_item_id = (string) $sub_item->getEntityId();
                    $this->import_log($sub_item_id, $recurse+1);

                    if ($property=='P15') { // statement means 'Related to' - so don't follow further
                        $item_out->children[] = $this->wiki_item_get($sub_item_id, false, 'Related');
                    } else { // follow away!
                        $item_out->children[] = $this->wiki_item_get($sub_item_id, $recurse+1);
                    }
                }
            }
        } elseif (!$item_out->link_property) {
            $item_out->link_property = 'Repeated';
        }
    }

    return $item_out;

    } catch ( DeserializationException $ex ) {
      echo ' DeserializationException!! ';
    } catch (UnresolvedEntityRedirectException $ex) {
      echo ' UnresolvedEntityRedirectException!! ';
    } catch (PropertyDataTypeLookupException $ex) {
      echo ' PropertyDataTypeLookupException!! ';
    } catch (OutOfBoundsException $ex) {
      echo ' OutOfBoundsException!! ';
    } catch (Exception $ex) {
      echo " Exception!! ";
    }

  }


  public function import_iterate($tree, $line_depth=1)
  {
      foreach ($tree as $key => $item) {
          if ($key=='parents' || $key =='meta') {
              continue;
          }

          //			$item_out = new StdClass;
          //	    	$item_out->name = $key;

          //			if(is_array($item) && isset($item['parent'])){
          if ($line_depth == $this->limit_depth) {
              if ($this->skip_until_after_tags) {
                  if (in_array($key, $this->skip_until_after_tags) && isset($this->skip_until_after_tags[$item['parents']['parent']])) {
                      $this->skip_tag_found = true;
                      continue;
                  } elseif (!$this->skip_tag_found) {
                      continue;
                  }
              }

              $tag_id = $this->import_tag_add($key, $item);
          }
          //	    	elseif($line_depth < $this->limit_depth && is_array($item))
          elseif (is_array($item)) {
              $this->import_iterate($item, $line_depth+1);
          }
      }

      return $items_out;
    }


    public function import_tag_add($tag, $minfo)
    {
        $m_src = $minfo['meta'];

        if ($m_src) {
            if ($m_src['UN3']) {
                $m_set['Code']['UN'] = $m_src['UN3'];
            } elseif ($m_src['UN2']) {
                $m_set['Code']['UN'] = $m_src['UN2'];
            } elseif ($m_src['UN1']) {
                $m_set['Code']['UN'] = $m_src['UN1'];
            } elseif ($m_src['UN0']) {
                $m_set['Code']['UN'] = $m_src['UN0'];
            }

            if ($m_src['NICE']) {
                $m_set['Code']['WIPO'] = $m_src['NICE'];
            }
            if ($m_src['eBay']) {
                $m_set['Code']['eBay'] = $m_src['eBay'];
            }
            if ($m_src['Google']) {
                $m_set['Code']['Google'] = $m_src['Google'];
            }
        }

        $parent = $minfo['parents']['parent'];
        $grandparent = $minfo['parents']['grandparent'];

        $this->import_log("ADD: $grandparent\t\t\t\t$parent\t\t\t\t$tag");

        unset($this->item);

        $tag_id = $this->tag_add($tag, $parent, $grandparent, $m_set);
        if (!$tag_id) {
            exit("\nCould not create tag '$tag' !");
        }
        echo $tag_id;

        return $tage_id;
    }


    function import_log($s, $recurse=0)
    {
      echo "
";
      for ($i=0; $i < $recurse; $i++) {
        echo "	";
      }
      echo $s;
      echo "
";
    }
}