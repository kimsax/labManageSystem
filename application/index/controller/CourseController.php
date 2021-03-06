<?php
namespace app\index\controller;
use app\common\model\Course;
use app\common\model\Teacher;
use think\Controller;
use think\facade\Request;


class CourseController extends Controller
{
    public function index()
    {
        //获取所有的教师信息
        $teachers = Teacher::all();
        $this->assign('teachers', $teachers);
        // 获取查询信息
        $name = Request::instance()->get('name');

        // 每页显示5条数据
        $pageSize = 5; 

        // 实例化Teacher
        $Course = new Course; 

        trace($Course, 'debug');

        //增加数据显示在第一个,按条件查询并分页
        $courses = $Course->where('name', 'like', '%' . $name . '%')->order('id desc')->paginate($pageSize, false, [
            'query'=>[
                'name' => $name,
                ],
            ]);
             
        $total_number = action($url = 'Administrator\noReadMessageNumber', $vars = "app\index\controller", $layer = 'controller', $appendSuffix = true);

        // 向V层传数据
        $this->assign('total_number', $total_number);
        $this->assign('courses', $courses);
        return $this->fetch();
    }

    public function update()
    {
        //获取ID
        $id = Request::instance()->post('id/d');

        // 获取传入课程信息
        $Course = Course::get($id);
        if (is_null($Course)) {
            return $this->error('系统未找到ID为' . $id . '的记录');
        }

        // 数据更新
        $Course->name = Request::instance()->post('name');
        $Course->teacher_id = Request::instance()->post('teacher_id/d');
        if (!$Course->save()) {  
            return $this->error('更新错误：' . $Course->getError());
        } else {
        // 进行跳转
         return $this->success('操作成功', url('index'));
        }
    	
    }

    public function delete()
    {

       // 获取ID
        $id = Request::instance()->param('id/d'); 

        if (is_null($id) || 0 === $id) {
            return $this->error('未获取到ID信息');
        }

        // 获取要删除的对象
        $Course = Course::get($id);

        // 要删除的对象不存在
        if (is_null($Course)) {
            return $this->error('不存在id为' . $id . '的课程，删除失败');
        }

       try {
           // 删除对象
           if (!$Course->delete()) {
               return $this->error('删除失败:' . $Course->getError());
           }

           // 进行跳转
           return $this->success('删除成功', url('index'));
       }catch (\think\exception\PDOException $e) {
            return $this->error('此课程与其他信息有关联，无法删除');
       }
    }
}