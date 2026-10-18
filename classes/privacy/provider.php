<?php
namespace mod_studymark\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('studymark_document',['userid'=>'privacy:metadata:userid'],'privacy:metadata:studymark_document');
        $collection->add_database_table('studymark_highlight',['userid'=>'privacy:metadata:userid','selectedtext'=>'privacy:metadata:content','note'=>'privacy:metadata:content'],'privacy:metadata:studymark_highlight');
        $collection->add_database_table('studymark_comment',['userid'=>'privacy:metadata:userid','content'=>'privacy:metadata:content'],'privacy:metadata:studymark_comment');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list=new contextlist();
        $sql="SELECT ctx.id FROM {context} ctx JOIN {course_modules} cm ON cm.id=ctx.instanceid AND ctx.contextlevel=:level JOIN {modules} m ON m.id=cm.module AND m.name='studymark' JOIN {studymark} s ON s.id=cm.instance LEFT JOIN {studymark_document} d ON d.studymarkid=s.id LEFT JOIN {studymark_highlight} h ON h.documentid=d.id LEFT JOIN {studymark_comment} c ON c.documentid=d.id WHERE d.userid=:u1 OR h.userid=:u2 OR c.userid=:u3";
        $list->add_from_sql($sql,['level'=>CONTEXT_MODULE,'u1'=>$userid,'u2'=>$userid,'u3'=>$userid]); return $list;
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $cm=get_coursemodule_from_id('studymark',$context->instanceid); if (!$cm) continue;
            $uid=$contextlist->get_user()->id; $path=[get_string('pluginname','studymark')];
            $sql="SELECT h.* FROM {studymark_highlight} h JOIN {studymark_document} d ON d.id=h.documentid WHERE d.studymarkid=? AND h.userid=?";
            writer::with_context($context)->export_data(array_merge($path,['highlights']),(object)['highlights'=>array_values($DB->get_records_sql($sql,[$cm->instance,$uid]))]);
            $sql="SELECT c.* FROM {studymark_comment} c JOIN {studymark_document} d ON d.id=c.documentid WHERE d.studymarkid=? AND c.userid=?";
            writer::with_context($context)->export_data(array_merge($path,['comments']),(object)['comments'=>array_values($DB->get_records_sql($sql,[$cm->instance,$uid]))]);
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) { global $DB; if ($context->contextlevel!==CONTEXT_MODULE) return; $cm=get_coursemodule_from_id('studymark',$context->instanceid); if (!$cm) return; $docs=$DB->get_fieldset_select('studymark_document','id','studymarkid=?',[$cm->instance]); if ($docs) { $DB->delete_records_list('studymark_comment','documentid',$docs); $DB->delete_records_list('studymark_highlight','documentid',$docs); } }
    public static function delete_data_for_user(approved_contextlist $contextlist) { global $DB; $uid=$contextlist->get_user()->id; foreach ($contextlist->get_contexts() as $context) { $cm=get_coursemodule_from_id('studymark',$context->instanceid); if (!$cm) continue; $docs=$DB->get_fieldset_select('studymark_document','id','studymarkid=?',[$cm->instance]); if ($docs) { list($insql,$params)=$DB->get_in_or_equal($docs,SQL_PARAMS_NAMED); $params['uid']=$uid; $DB->delete_records_select('studymark_comment',"userid=:uid AND documentid $insql",$params); $DB->delete_records_select('studymark_highlight',"userid=:uid AND documentid $insql",$params); } } }
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) { return; }
        $sql = "SELECT d.userid FROM {course_modules} cm JOIN {studymark} s ON s.id=cm.instance JOIN {studymark_document} d ON d.studymarkid=s.id WHERE cm.id=:cmid
                UNION SELECT h.userid FROM {course_modules} cm JOIN {studymark} s ON s.id=cm.instance JOIN {studymark_document} d ON d.studymarkid=s.id JOIN {studymark_highlight} h ON h.documentid=d.id WHERE cm.id=:cmid2
                UNION SELECT c.userid FROM {course_modules} cm JOIN {studymark} s ON s.id=cm.instance JOIN {studymark_document} d ON d.studymarkid=s.id JOIN {studymark_comment} c ON c.documentid=d.id WHERE cm.id=:cmid3";
        $userlist->add_from_sql('userid', $sql, ['cmid'=>$context->instanceid,'cmid2'=>$context->instanceid,'cmid3'=>$context->instanceid]);
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context=$userlist->get_context(); if ($context->contextlevel!==CONTEXT_MODULE) return;
        $cm=get_coursemodule_from_id('studymark',$context->instanceid); if (!$cm) return;
        $docs=$DB->get_fieldset_select('studymark_document','id','studymarkid=?',[$cm->instance]); $users=$userlist->get_userids();
        if (!$docs || !$users) return;
        [$dsql,$dparams]=$DB->get_in_or_equal($docs,SQL_PARAMS_NAMED,'d'); [$usql,$uparams]=$DB->get_in_or_equal($users,SQL_PARAMS_NAMED,'u');
        $DB->delete_records_select('studymark_comment',"documentid $dsql AND userid $usql",$dparams+$uparams);
        $DB->delete_records_select('studymark_highlight',"documentid $dsql AND userid $usql",$dparams+$uparams);
    }}
