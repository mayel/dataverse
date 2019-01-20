<?php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use RedBeanPHP\R;

class Responses extends Admin
{

    public function response_action_button($link, $class='success')
    {
        $this->the_response_tag_id = false;
        return "<a href='$link' class='btn btn-sm btn-$class pull-right'>Confirm</a>";
    }

    public function list_respondents_responses($questionnaire_id, $page, $sort_by, $sorting, $has_email_field=false, $include_personal_info=true)
    {
        global $bv;

        $this->questionnaire_id = $questionnaire_id ? $questionnaire_id : $this->session->get('questionnaire'); // get from session

        // R::debug();

        if ($has_email_field) {
            $count = R::count('respondent', ' questionnaire_id = ? AND email IS NOT NULL ', [ $this->questionnaire_id ]);
        } else {
            $count = R::count('respondent', ' questionnaire_id = ? ', [ $this->questionnaire_id ]);
        }

        if (!$count) {
            return [];
        }

        $this->session->set('questionnaire', $this->questionnaire_id); // save as session

        $limits = ($this->conf->db_type == 'postgres' ? ' LIMIT ? OFFSET ? ' : ' LIMIT ? , ? ');

        $per_page = 50;

        if ($has_email_field) {
            $people = R::find('respondent', " questionnaire_id = ? AND email IS NOT NULL ORDER BY $sort_by $sorting ", [ $this->questionnaire_id ]);
        } // list all with email
        else {
            $people = R::find('respondent', " questionnaire_id = ? ORDER BY $sort_by $sorting ", [ $this->questionnaire_id ]);
        } // list all

        if ($people) {
            foreach ($people as $p) {

                $responses[] = $this->respondent_responses($p->id);
            }

            $paginator  = $this->get('knp_paginator');
            $responses = $paginator->paginate(
              $responses, /* ideally query NOT result */
              $page/*page number*/,
              $per_page/*limit per page*/
          );


            return $responses;
        }
    }

    public function list_respondents_questions_responses($questionnaire_id = 1, $page = 1, $sort_by = 'ts_started', $sorting = 'desc', $include_personal_info=false)
    {

        $this->questionnaire = $this->questionnaire_get($questionnaire_id);
        // var_dump($questionnaire_id, $this->questionnaire->id, $this->questionnaire);

        if($this->questionnaire && $this->questionnaire->id){
            
            $questions = $this->questionnaire_questions($this->questionnaire->id); // load all questions

            foreach ($questions as $q) {
                if ($q->answer_type=='Email') {
                    $has_email_field = true;
                }

                if ($q->answer_type=='Include') {
                    @include_once($this->conf->base_path.'public_pages/'.$q->question_name);
                }

                if (in_array($q->answer_type, ['Notice','Include','Password'])) {
                    continue;
                }
                if (!$include_personal_info && in_array($q->answer_type, ['Email','Phone'])) {
                    continue;
                }

                $this->questions[$q->id] = $q;
            }

            $this->responses = $this->list_respondents_responses($this->questionnaire->id, $page, $sort_by, $sorting, $has_email_field, $include_personal_info);

        }


        return $this->responses;
    }

    /**
    * @Route("/responses/{questionnaire_id}/{page}/{sort_by}/{sorting}", name="list_responses", requirements={"questionnaire_id"="\w+", "page"="\d+", "sort_by": "[a-zA-Z0-9_]+", "sorting": "asc|desc"})
    */
    public function list_responses($questionnaire_id = 1, $page = 1, $sort_by = 'ts_started', $sorting = 'desc', $include_personal_info=false, $include_q_selector=false)
    {
        if (!$this->member_auth(false) && !$this->admin_auth(false)) {
            $this->questionnaire_auth($questionnaire_id, true);
        }

        $questionnaires_list = $this->questions = $responses = [];

        $this->list_respondents_questions_responses($questionnaire_id, $page, $sort_by, $sorting, $include_personal_info);

        if($include_q_selector) $questionnaires_list = $this->questionnaires();

        return $this->render('admin/table-responses.html.twig', array(
            'cols' => $this->questions,
            'items' => $this->responses,
            'pagination' => $this->pagination,
            'questionnaire_id' => $this->questionnaire->id,
            'questionnaire_name' => $this->questionnaire->questionnaire_name,
            'questionnaire_tile' => $this->questionnaire->questionnaire_title,
            'questionnaires_list' => $questionnaires_list
        ));
    }

    /**
    * @Route("/admin/responses/{questionnaire_id}/{page}/{sort_by}/{sorting}", name="admin_responses", requirements={"questionnaire_id"="\w+", "page"="\d+", "sort_by": "[a-zA-Z0-9_]+", "sorting": "asc|desc"})
    */
    public function admin_responses($questionnaire_id = 1, $page = 1, $sort_by = 'ts_started', $sorting = 'desc')
    {
        return $this->list_responses($questionnaire_id, $page, $sort_by, $sorting, true, true);
    }
}
