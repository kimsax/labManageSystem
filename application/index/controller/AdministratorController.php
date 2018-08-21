<?php
namespace app\index\controller;

use app\common\model\Administrator;
use app\common\model\Changelesson;
use app\common\model\Classroom;
use app\common\model\Course;
use app\common\model\Klass;
use app\common\model\Log;
use app\common\model\Sechedule;
use think\Controller;
use think\facade\Request;

/*
 * 管理员页面和个人信息页面的功能
 *
 * */
class AdministratorController extends Controller
{
    private $sechedule;
    public function __construct()
    {
        parent::__construct();
        $this->sechedule = Sechedule::where('semester', '=', '2018/01');
        $this->sechedule = $this->sechedule->where('weekly', '=', 1);
        $this->sechedule = $this->sechedule->where('classroom_num', '=', 1);

    }
    //index页面
    public function index()
    {

        //初始化设置
        $onWeekly = 1;
        $onClassroom = 1;
        $Courses = Course::select();
        $Klasses = Klass::select();

        $postData = Request::instance()->post();
        //查询条件
        if (!empty($postData)) {
            $this->sechedule = Sechedule::where('semester', '=', '2018/01');
            $this->sechedule = $this->sechedule->where('weekly', '=', (int) $postData['weekly']);

            $onWeekly = (int) $postData['weekly'];
            $this->sechedule = $this->sechedule->where('classroom_num', '=', (int) $postData['classroom_num']);
            $onClassroom = (int) $postData['classroom_num'];
        }
        $weekList = $this->editSechedule();

        $this->assign('weekList', $weekList);
        $allClassroom = Classroom::select();
        $this->assign('allClassroom', $allClassroom);

        //向v层传送数据
        $this->assign('Klasses', $Klasses);
        $this->assign('Courses', $Courses);
        //$this->assign('Teacher',$Teacher);
        $this->assign('onWeekly', $onWeekly);
        $this->assign('onClassroom', $onClassroom);

        return $this->fetch();
    }

    public function editSechedule()
    {
        $weekList = array();
        for ($i = 1; $i <= 5; $i++) {
            $nodeList = array(); //节数组
            //划定每节范围
            $temp = clone $this->sechedule;
            $temp = $temp->where('node', '=', $i);
            $weeklyList = $temp->select();
            foreach ($weeklyList as $weekly) {
                $nodeList[$weekly['week']] = $weekly;
            }
            ksort($nodeList);
            array_push($weekList, $nodeList);
        }
        return $weekList;
    }
    //管理员个人信息界面
    public function personalinformation()
    {
        $Administrator = new Administrator();
        $Administrator = Administrator::get(1);

        //向v层传数据
        $this->assign('Administrator', $Administrator);
        return $this->fetch("personalInformation");
        return $this->fetch("creatCode");
    }

    //保存管理员提交的个人信息
    public function save()
    {
        $id = Request::instance()->post('id');
        $Administrator = Administrator::get($id);
        //存储
        $Administrator->name = input('post.name');
        $Administrator->password = input('post.password');
        $Administrator->save();
        return $this->success('操作成功', url('index'));
    }

    //生成二维码
    public function creatcode()
    {
        return $this->fetch("creatcode");
    }

    //换课申请消息界面
    public function message()
    {
        // 获取查询信息
        $name = Request::instance()->get('name');

        // 设置每页大小
        $pageSize = 5;
        //从换课申请表中找到待审核消息
        $Changelessons = new Changelesson;
        // 按条件查询数据并调用分页
        $changelessons = $Changelessons
            ->where('state', '=', 1)
            ->where('targetsechedule_id', 'like', '%' . $name . '%')
            ->order('id', 'desc')
            ->paginate($pageSize, false, [
                'query' => [
                    'targetsechedule_id' => $name,

                ],
            ]);

        //向v层传送数据
        $this->assign('changelessons', $changelessons);
        return $this->fetch("message");
    }

    //处理请求
    public function handlemessage($id, $request)
    {
        $Changelesson = Changelesson::get($id);
        if ($request == 1) {
            $Changelesson->state = 3; //修改状态为管理员已同意
            $Changelesson->save(); //保存修改
            $this->creatlog($Changelesson);//生成日志
            Sechedule::exchange($Changelesson->applysechedule_id, $Changelesson->targetsechedule_id); //换课
            return $this->success('操作成功,已同意该请求', url('message'));
        } else if ($request == 0) {
            $Changelesson->state = 4; //修改状态为管理员已拒绝
            $Changelesson->save(); //保存修改
            $this->creatlog($Changelesson);//生成日志
            return $this->success('操作成功,拒绝该请求', url('message'));
        } else {
            return $this->error('操作失败,请重试', url('message'));
        }
    }

    // 生成日志
    public function creatlog($changelesson)
    {
        $log = new Log;

        $log->applyinformation = $changelesson->getApply()->teacher->name . '-第' . $changelesson->getApply()->weekorder . '周' . '-星期' . $changelesson->getApply()->week . '-第' . $changelesson->getApply()->node . '节-' . $changelesson->getApply()->classroom->name;

        $log->targetinformation = $changelesson->getTarget()->teacher->name . '-第' . $changelesson->getTarget()->weekorder . '周' . '-星期' . $changelesson->getTarget()->week . '-第' . $changelesson->getTarget()->node . '节-' . $changelesson->getTarget()->classroom->name;

        $log->state = $changelesson->getData('state');

        $log->save();
        return '日志生成成功';
    }

    //换课日志
    public function log()
    {
        // 获取查询信息
        $information = Request::instance()->get('information');

        // 设置每页大小
        $pageSize = 5;

        // 实例化Classroom
        $Log = new Log;
        
        // 按条件查询数据并调用分页
        $logs = $Log
        ->where('applyinformation', 'like', '%' . $information . '%')
        ->order('id', 'desc')
        ->paginate($pageSize, false, [
            'query'=>[
                'applyinformation' => $information,
                ],
            ]);                 
        // 向V层传数据
        $this->assign('logs', $logs);

        // 取回打包后的数据
        $htmls = $this->fetch();

        // 将数据返回给用户
        return $htmls;       
    }
    
}
