<?php
/*------------------------------------------------------------------------
# Cobalt
# ------------------------------------------------------------------------
# @author Cobalt
# @copyright Copyright (C) 2012 cobaltcrm.org All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Website: http://www.cobaltcrm.org
-------------------------------------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' ); 

class CobaltViewGoalsRaw extends JViewHtml
{
    function render($tpl = null)
    {

        $app = JFactory::getApplication();

        //if we are deleting goals determine which goals the user is allowed to delete
        if ( $this->getLayout() == 'delete' ){
            
            //get type of goal we are requesting to delete
            $type = $app->input->get('goal_type');
            
            //get member role to determine what goals the user can see
            $member_role = CobaltHelperUsers::getRole();
            
            //get model
            $model = new CobaltModelGoal();
            
            //show individual goals
            if ( $type == 'member' ){
                //show goals for exectuvies
                if ( $member_role == 'exec' ){
                    $goals = $model->getExecIndividualGoals();
                }
                
                //show goals for managers
                if ( $member_role == 'manager' ){
                    $goals = $model->getManagerIndividualGoals();
                }
                
                //show goals for basic users
                if ( $member_role == 'basic' ){
                    $goals = $model->getIndividualGoals();
                }
            }
            
            //show team goals
            if ( $type == 'team' ){
                //show all goals to exectuvies
                if ( $member_role == 'exec' ){
                    $goals = $model->getExecTeamGoals();
                }else{
                    $goals = $model->getTeamGoals();
                }
            }
            
            //show company goals
            if ( $type == 'company' ){
                $goals = $model->getCompanyGoals();
            }
            
            //assign view references
            $this->goals = $goals;
            
        }
        
        //display
        echo parent::render();
    }
    
}