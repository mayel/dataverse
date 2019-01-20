<?php
namespace App\Controller;

use RedBeanPHP\R;

class Frontend extends App
{
    public function field_params($params=[])
    {
        return array_merge($this->field_params, ['label' => $this->field_label, 'attr' => $this->attr, 'data' => $this->field_value], $params);
    }

    public function usort_by_ts_added($a, $b)
    {
        return strcmp($a->ts_added, $b->ts_added);
    }

    public function respondent_questions_responses_save($data)
    { // save object in DB, with support for many to many for items with array of data

        //$this->response = R::dispense( $this->db_tables->response ); // init response

        $this->logger->info('respondent_questions_responses_save()', [$data, $this->questions_by_field]);

        foreach ($data as $key => $value) {
            // var_dump($key , $value, $this->questions_by_field[$key]->question_text);

            if ($this->questions_by_field[$key]) { // to handle multiple questions on one screen
                $this->question = $this->questions_by_field[$key];
            }

            $this->respondent_question_responses_save($key, $value, $data);
        }
        // exit();
    }

    public function respondent_question_responses_save($key, $value, $data)
    { // save object in DB, with support for many to many for items with array of data


        $this->field_name = ($this->question->question_name ? $this->question->question_name : $this->question->id);
        $this->answer_type = ($this->question->answer_type);

        if ($this->answer_type=='Sortable') { // special case when dealing with an ajax list-sorting
            $ord=0;

            if (is_array($value)) {
                foreach ($value as $so) {
                    //print_r($so);
                    if ($so->id) {
                        $answer = answer_prepare($so->id, true);

                        if ($answer) {
                            if ($ord>0) {
                                unset($this->response);
                            }  // create an additional response row

                            $respond[$this->col_prefix.'Num'] = $ord;

                            $response_ids[] = answer_response_save($answer, $respond);

                            $ord++;
                        }
                    }
                }
                exit('sorted '.$ord);
            } else {
                return true;
            }
        } elseif ($key == $this->field_name) { // dealing with the response to current question

            // defaults:
            $try_by_id=false;
            unset($col_name);

            if ($this->answer_type=='TaxonomyTag') {
                $try_by_id=false;
            } // workaround

            if ($value) {
                if (is_array($value) && count($value)<2) {
                    $value = current($value);
                }

                if (in_array($this->answer_type, ['UploadImage','UploadDoc','UploadFile'])) {
                    $dir = $this->conf->base_path.'custom/uploads';
                    $file = $value;

                    // compute a random name and try to guess the extension (more secure)
                    $extension = $file->guessExtension();
                    if (!$extension) {
                        // extension cannot be guessed
                        $extension = 'bin';
                    }

                    $filename = $this->respondent->id.'_'.rand(1, 99999).'.'.$extension;

                    $file->move($dir, $filename);

                    $value = $filename;
                    $col_name = 'Var';
                } elseif ($this->answer_type=='Email') { // save email in main 'respondent' table
                    $this->respondent->email = $value;
                    R::store($this->respondent);

                    $this->session->set('respondent_email', $value); // save in cookie

                    $col_name = 'Var';
                } elseif ($this->answer_type=='MapLocation') {
                    $col_name = 'Point';
                    $geo_col = $this->db_tables->response.'.'.$this->col_prefix.'_'.'point';
                    // R::bindFunc('read', $geo_col, 'asText'); // TODO
                    R::bindFunc('write', $geo_col, 'GeomFromText');
                    $point_num = str_replace(',', ' ', $value);
                    $value = "POINT($point_num)";
                } elseif ($this->answer_type=='Currency') {
                    $this->currency_set($value); // save selected currency in session
                } elseif (in_array($this->answer_type, ['MultipleChoices','Choice','Dropdown'])) { // form can submit IDs of answers rather than contents
                    $try_by_id=true;
                } elseif ($value instanceof DateTime || is_a($value, 'DateTime')) { // Date, DateTime, or Time
                    $date = $value->format('Y-m-d');
                    $time = $value->format('H:i:s');
                    if ($date=='1970-01-01') {
                        $col_name = 'Time';
                        $value = $time;
                    } elseif ($time=='00:00:00') {
                        $col_name = 'Date';
                        $value = $date;
                    } else {
                        $col_name = 'DateTime';
                        $value = $value->format('Y-m-d H:i:s');
                    }
                } elseif (in_array($this->answer_type, ['Phone','LongText'])) {
                    $col_name = 'Var';
                } elseif ($this->answer_type=='Password') {
                    $col_name = 'Var';
                // TODO
                } elseif (is_numeric($value)) {
                    $col_name = 'Num';
                }

                if ($col_name && $this->col_prefix) {
                    $col_name = $this->col_prefix.$col_name;
                }

                // var_dump($value, $col_name, $this->answer_type, $try_by_id);
                // exit();

                if ($this->answer_type=='Price') { // both number & currency

                    $this->currency_get(); // load existing cookie
                    if ($data['currency']) {
                        $this->currency_set($data['currency']);
                    } // set new cookie if selected

                    $respond[$col_name] = $value; // Price amount

                    $answer = $this->answer_prepare($this->currency); // get currency ID

                    $response_ids[] = $this->answer_response_save($answer, $respond); // save Price & currency ID

                    $this->response_save_custom($value); // amount
                    $this->response_save_custom($this->currency, 'currency'); // currency code
                } elseif ($col_name && !$try_by_id) { // simply store in appropriate column of response table

                    $respond[$col_name] = $value; // store

                    $response_ids[] = $this->response_save($respond);

                    $this->response_save_custom($value);
                } elseif (is_array($value)) { // store (multiple answers) in answer table

                    $try_by_id=true;
                    $multii=0;

                    foreach ($value as $linked_value) {
                        if ($linked_value) {
                            $answer = $this->answer_prepare($linked_value, $try_by_id);

                            if ($answer) {
                                if ($multii>0) {
                                    unset($this->response);
                                }  // so an additional response row gets created

                                $response_ids[] = $this->answer_response_save($answer);

                                $answers[] = $answer;

                                $multii++;
                            }
                        }
                    }

                    $this->response_save_custom($answers);
                } else { // store (single answer) in answer table

                    if ($value) {
                        $answer = $this->answer_prepare($value, $try_by_id);
                    }

                    if ($answer) {
                        $response_ids[] = $this->answer_response_save($answer);
                        $this->response_save_custom($answer);
                    }
                }
            } // end if value
        } elseif ($this->answer_type=='Price' && $key=='currency') {

            // already dealt with above
        } elseif ($value) { // other regular field

            $respond[$key] = $value; // store
            $response_ids[] = $this->response_save($respond);
            //response_save_custom($value);
        }

        // exit();
        return $response_ids;
    }


    public function answer_prepare($value, $try_by_id=false)
    {
        if ($try_by_id) {
            $answer = $this->answer_get($value);
        } // find pre-existing answer by ID

        if (!$answer->id) {
            $answer = $this->answer_find($value); // find pre-existing answer by content

            //print_r($value, $answer);

            if (!$answer->id) { // create new answer

                $answer = R::dispense('answer');

                $answer->answer = $value;
                $answer->ts_added = R::isoDateTime();
                if ($this->respondent) {
                    $answer->by_respondent = $this->respondent;
                }

                R::store($answer);
            }
        }

        return $answer;
    }

    public function answer_response_save($answer, $respond=[])
    {
        $respond['answer'] = $answer; // store relation

        return $this->response_save($respond);
    }

    public function response_save($respond)
    {
        try {
            if ($respond['answer']) {
                $this->logger->info('modify past answer:', [$respond]);

                $find = [ 'respondent_id' => $this->respondent->id, 'question_id' => $this->question->id, 'answer_id' => $respond['answer']->id ];
                $this->response = R::findOrCreate($this->db_tables->response, $find);
            } else {
                $this->logger->info('prepare for new response:', [$respond]);
                $this->response = R::dispense($this->db_tables->response);
            }

            $this->response->respondent = $this->respondent; // ownership
            $this->response->question = $this->question; // link to question

            $this->response->response_ts = R::isoDateTime();

            $this->response->import($respond);

            //$this->response->setMeta("buildcommand.unique", array(array('respondent', 'question', 'answer')));

            $id = R::store($this->response); // save

            unset($this->response);
            //exit($id);
            return $id;
        } catch (Exception $e) {
            $this->logger->error('Could not save a response (probably duplicate', [$e]);
            // TODO
        }
    }

    public function response_save_custom($data, $custom_field_name=false)
    {
        try {
            return; // TODO! need to figure out respondent as unique ID

            if ($data && $this->questionnaire->questionnaire_name && ($custom_field_name || $this->field_name)) { // also save to dedicated table for current questionnaire

                $this->item = $this->data_by_respondent($this->questionnaire->questionnaire_name, $this->respondent->id);
                //var_dump($this->item);

                //			$find_t = [ 'respondent' => $this->respondent ];
                //			$this->item = R::findOrCreate($this->questionnaire->questionnaire_name, $find_t);

                $data_a[($custom_field_name ? $custom_field_name : $this->field_name)] = $data;
                $data_a['respondent'] = $this->respondent;
                $data_a['updated_ts'] = $this->response->response_ts;

                return $this->item_save($this->questionnaire->questionnaire_name, $data_a, ['answer'=>'answer']);
            }

            //exit($id);
            return $id;
        } catch (Exception $e) {
            $this->logger->error('Could not save a response (probably duplicate)', [$e]);
            // TODO
        }
    }

    public function currency_get()
    {
        $this->currency = $this->session->get('currency');
        return $this->currency;
    }

    public function currency_set($currency)
    {
        if ($currency) {
            $this->currency = $currency;
            $this->session->set('currency', $currency); // save
        }
    }

    public function questionnaires()
    {
        return R::find('questionnaire');
    }

    public function questionnaire_get($id)
    {
        if(is_numeric($id)) $q = $this->data_by_id('questionnaire', $id); // try by ID

        if(!$q) $q = R::findOne('questionnaire', ' questionnaire_name = ? ', [ $id ]); // try by name

        $this->questionnaire_id = $q->id; // override name as ID

        return $q;
    }

    public function questionnaire_questions($id)
    {
        return R::find('question', ' questionnaire_id = ? ORDER BY id ASC ', [ intval($id) ]);
    }

    public function question_get($id)
    {
        return $this->data_by_id('question', $id);
    }

    public function respondent_get($id)
    {
        return $this->data_by_id($this->db_tables->respondent, $id);
    }

    public function respondent_find($value, $field='email')
    {
        return $this->get_by_field($this->db_tables->respondent, $field, $value);
    }

    public function answer_get($id)
    {
        return $this->data_by_id('answer', $id);
    }

    public function answer_find($value)
    {
        return $this->get_like_field('answer', 'answer', $value);
    }

    public function data_by_respondent($table, $respondent_id)
    {
        return R::findOne($table, ' respondent_id = ? ORDER BY response_ts DESC ', [ intval($respondent_id) ]);
    }

    public function response_by_question_id($question_id, $respondent_id)
    {
        $geo_col = $this->db_tables->response.'.'.$this->col_prefix.'_'.'point';
        // R::bindFunc('read', $geo_col, 'asText'); // TODO
        return R::findOne($this->db_tables->response, ' question_id = ? AND respondent_id = ? ORDER BY response_ts DESC ', [ intval($question_id), intval($respondent_id) ]);
    }

    public function respondents_by_status($status)
    {
        return R::find($this->db_tables->respondent, ' status = ? ', [ $status ]);
    }

    public function a_respondent_by_status($status)
    {
        return $this->get_like_field($this->db_tables->respondent, 'status', $status);
    }

    public function questionnaire_steps($id)
    {
        return R::find('step', 'questionnaire_id = ? ORDER BY step ASC, step_order ASC ', [ intval($id) ]);
    }

    public function questionnaire_step($step)
    {
        return R::find('step', 'questionnaire_id = ? AND step >= ? ORDER BY step_order ASC', [ intval($this->questionnaire->id), $step]);
    }

    public function questionnaire_next_step($step)
    {
        return R::findOne('step', 'questionnaire_id = ? AND step > ? LIMIT 1 ', [ intval($this->questionnaire->id), $step]);
    }

    public function questionnaire_last_step()
    {
        return R::findOne('step', 'questionnaire_id = ? ORDER BY step DESC, step_order DESC LIMIT 1 ', [ intval($this->questionnaire->id) ]);
    }

    public function step_get($id)
    {
        return $this->data_by_id('step', $id);
    }

    public function steps_by_question_id($id)
    {
        return R::find('step', ' question_id = ? ', [ intval($id) ]);
    }

    public function username_by_respondent_id($respondent_id)
    { // get username
        $r = $this->response_by_question_id($this->conf->question_id_username, $respondent_id);
        // var_dump($this->conf->question_id_username, $respondent_id, $r);
        return $r->the_var ? $r->the_var : $r->answer->answer;
    }

    public function geo_point_to_array($point_from_db)
    {
        preg_match('/([0-9.-]+).+?([0-9.-]+)/', $point_from_db, $matches);
        return [ (float)$matches[1], (float)$matches[2] ]; // lat/long
    }

    public function response_value($r)
    { // get responses from DB, with support for many to many for items with array of data

        $the_response = $r->the_var;

        if (!$the_response) {
            $the_response = $r->the_num;
        }
        if (!$the_response) {
            $the_response = $r->the_date;
        }
        if (!$the_response) {
            $the_response = $r->the_date_time;
        }
        if (!$the_response) {
            $the_response = $r->the_time;
        }

        $the_answer_id = $r->answer_id;

        if (!$the_response && $r->answer && $r->answer->id) {
            $the_response = $r->answer->answer;
            $the_answer_id = $r->answer->id;
        }

        if (!$the_response) {
            // R::bindFunc('read', 'response.the_point', 'asText');
            $the_response = $r->the_point;

            if ($the_response) {
                list($lat, $long) = $this->geo_point_to_array($the_response);

                if ($lat) {
                    $the_response = "<a href='https://www.openstreetmap.org/?mlat=$lat&mlon=$long&zoom=12#layers=M' target='_blank'>$lat, $long</a>";
                }
            }
        }

        // var_dump($the_answer_id, $the_response, $r->question);

        if (is_numeric($the_response) && $r->question && $r->question->answer_type=='TaxonomyTag') { // taxonomy tag

            $this->the_response_tag_id = $the_response;

            $tx = $this->get('Taxonomy');
            $the_tag = $tx->tag_name_with_ancestors($this->the_response_tag_id, $seperator=' ≫ ');

            if ($the_tag) {
                $the_response = "<a class='question-".$r->question->question_name."' href='/taxonomies?tag_id=$this->the_response_tag_id' target='_blank'>$the_tag</a>";
            }
        }

        if ($this->the_response_tag_id) {

            // echo $this->questionnaire->questionnaire_name." - ".$r->question->question_name;

            if ($r->question->question_name=='tag_new_label') { // new tag
                $the_response .= $this->response_action_button("/taxonomy/tag/". $this->the_response_tag_id ."/new?format=redirect&label=". urlencode($the_response));
            }

            if ($this->questionnaire->questionnaire_name=='tag_move') { // delete tag

                if ($this->move_from_tag) {
                    $the_response .= $this->response_action_button("/taxonomy/tag/". $this->move_from_tag ."/edit?parent_tag=". $this->the_response_tag_id ."&format=redirect", 'warning');
                    $this->move_from_tag = false;
                } else {
                    $this->move_from_tag = $this->the_response_tag_id;
                }
            }

            if ($this->questionnaire->questionnaire_name=='tags_relation') { // delete tag

                if ($this->link_tag_1) {
                    $the_response .= $this->response_action_button("/taxonomy/tag/". $this->link_tag_1 ."/edit?related_tag=". $this->the_response_tag_id ."&format=redirect", 'warning');
                    $this->link_tag_1 = false;
                } else {
                    $this->link_tag_1 = $this->the_response_tag_id;
                }
            }

            if ($r->question->question_name=='tag_label_new') { // rename tag
                $the_response .= $this->response_action_button("/taxonomy/tag/". $this->the_response_tag_id ."/edit?format=redirect&label=". urlencode($the_response), 'danger');
            }

            if ($this->questionnaire->questionnaire_name=='tag_delete') { // delete tag
                $the_response .= $this->response_action_button("/taxonomy/tag/". $this->the_response_tag_id ."/delete?format=redirect", 'danger');
            }

        }

        // $the_response .= " // A: ".$this->the_response_tag_id." T: ".$r->question->answer_type." N: ".$this->questionnaire->questionnaire_name." ID: ".$this->the_response_tag_id." Q: ".$r->question->question_name;


        return [$the_answer_id, $the_response];
    }

    public function response_action_button($link, $class='success')
    {
        $this->the_response_tag_id = false;
        // return "<a href='$link' class='btn btn-sm btn-$class pull-right'>Confirm</a>";
    }


    public function respondent_responses($respondent_id){
        global $bv;

        R::bindFunc('read', 'response.the_point', 'asText');

        $resp_data = R::find('response', ' respondent_id = ?
        ORDER BY response_ts ASC', [ $respondent_id ]);
        // var_dump($resp_data);

        $pr = [];
        foreach ($resp_data as $r) {
            list($key, $c) = $this->response_value($r);

            if ($r->question_id) {
                
                if ($c && $this->questions[$r->question_id] && $this->questions[$r->question_id]->question_name && $bv->preload_choices[$this->questions[$r->question_id]->question_name] && $bv->preload_choices[$this->questions[$r->question_id]->question_name][$c]) {
                    $c = $bv->preload_choices[$this->questions[$r->question_id]->question_name][$c];
                }

                if ($pr[$r->question_id] && !is_array($pr[$r->question_id])) { // first of multiple answers
                    $first_a = $pr[$r->question_id];
                    $pr[$r->question_id] = [];
                    $pr[$r->question_id][] = $first_a;
                    $pr[$r->question_id][] = $c;
                } elseif ($pr[$r->question_id]) { // next multiple answers
                    $pr[$r->question_id][] = $c;
                } else {
                    $pr[$r->question_id] = $c;
                }
            }
        }

        return $pr;
    }

    public function combine_responses_with_question_names($questionnaire_id, $responses=[], $include_personal_info=false){

        $questions = $this->questionnaire_questions($questionnaire_id); // load all questions

        foreach ($questions as $q) {
            if ($q->answer_type=='Email') {
                $has_email_field = true;
            }

            // if ($q->answer_type=='Include') {
            //     @include_once($this->conf->base_path.'public_pages/'.$q->question_name);
            // }

            if (in_array($q->answer_type, ['Notice','Include','Password'])) {
                continue;
            }
            if (!$include_personal_info && in_array($q->answer_type, ['Email','Phone'])) {
                continue;
            }

            $this->question_details[$q->id] = $q;

            if($responses[$q->id]) $r[$q->question_text] = $responses[$q->id];
        }

        return $r;
    }

    public function array_to_plaintext($ar=[]){
        if(is_array($ar)) foreach($ar as $a => $b){
            $t .= "$a: $b  \n";
        }
        return $t;
    }

}
