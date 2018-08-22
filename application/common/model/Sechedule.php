<?php
namespace app\common\model;

use think\Model;

/**
 * 时间教室表
 */
class Sechedule extends Model
{
    //
	public function klasses()
    {
        return $this->belongsToMany('Klass');
    }
    public function teacher(){
    	return $this->belongsTo('Teacher');
    }
    public function course(){
    	return $this->belongsTo('Course');
    }
    /**
    *行程与教室一对多关联
    */	

    public function classroom()
    {
        return $this->belongsTo('Classroom');
    }

    /**
    *判断行程是否在换课中
    *@return boolean
    */
    public function isChangeLesson()
    {
        $applySechedule=Changelesson::get(['applysechedule_id'=>$this->id]);
        $targetSechedule=Changelesson::get(['targetsechedule_id'=>$this->id]);
        if(isset($applySechedule)||isset($targetSechedule)){
            return true;
        }else{
            return false;
        }
    }

    //判断中间表中是否存在该关联
    public function getKlassesIsChecked(Klass &$Klass)
    {

        //取id
        $secheduleId = $this->id;
        $klassId = $Klass->id;

        //定制查询条件
        $map = array();
        $map['klass_id'] = $klassId;
        $map['sechedule_id'] = $secheduleId;

        //从关联表中取信息
        $SecheduleKlass = SecheduleKlass::get($map);

        //判断是否存在
        if (is_null($SecheduleKlass)) {
            return false;
        } else {
            return true;
        }

    }

    //传入周次，星期，节次，教室,查询在sechedule表里对应的对象并返回该对象的id
    public static function findtarget($weekly, $week, $node, $classroom_num)
    {
        $target = Sechedule::where([
            ['weekly', '=', $weekly],
            ['week', '=', $week],
            ['node', '=', $node],
            ['classroom_num', '=', $classroom_num],
        ])->select();
        return $target[0]['id'];
    }

    //找到sechedule_klass表里所有sechedule_id为id的对象
    public function getKlasses()
    {
        return SecheduleKlass::where('sechedule_id', '=', $this->id)->select();
    }

    //传入交换课和目标课的id，交换两课的教师，课程，班级
    public static function exchange($id, $targetid) //id为交换课的id，target为目标课的id

    {
        $ApplySechedule = Sechedule::get($id); //通过id，找到sechedule表里对应的对象
        $ApplyKlass = $ApplySechedule->getKlasses(); //找到sechedule_klass表里所有sechedule_id为id的对象，存到数组$Change中
        $TargetSechedule = Sechedule::get($targetid); //通过targetid，找到sechedule表里对应的对象
        //新建中间变量,用于交换教师和课程
        $Trans = new Sechedule();
        //交换教师
        $Trans->teacher_id = $ApplySechedule->teacher_id;
        $ApplySechedule->teacher_id = $TargetSechedule->teacher_id;
        $TargetSechedule->teacher_id = $Trans->teacher_id;
        //交换课程
        $Trans->course_id = $ApplySechedule->course_id;
        $ApplySechedule->course_id = $TargetSechedule->course_id;
        $TargetSechedule->course_id = $Trans->course_id;
        //交换班级
        $ApplyKlass = $ApplySechedule->getKlasses();
        $TargetKlass = $TargetSechedule->getKlasses();
        $t = $TargetKlass[0]['sechedule_id']; //t为交换的中间变量
        for ($i = 0; $i < count($TargetKlass); $i++) {
            $TargetKlass[$i]['sechedule_id'] = $ApplyKlass[0]['sechedule_id'];
            $TargetKlass[$i]->save();
        }
        for ($i = 0; $i < count($ApplyKlass); $i++) {
            $ApplyKlass[$i]['sechedule_id'] = $t;
            $ApplyKlass[$i]->save();
        }
        //保存
        $ApplySechedule->save();
        $TargetSechedule->save();
    }
  
   /*
    * 找到相同时间的其他教室
    * */
    public function findTheSameTimeSechedule(Sechedule &$Sechedule)
    {
        //定制查询条件
        $map = array();
        $map['weekorder'] = $Sechedule->weekorder;
        $map['node'] = $Sechedule->node;
        $map['week'] = $Sechedule->week;
        $map['semester_id'] = $Sechedule->semester_id;

        //找到相同时间的教室
        $sechedules = $Sechedule->where($map)->select();

        return $sechedules;
    }

    /*
     * 判断老师和学生是否在这个教室
     * */
    public function isExist(Sechedule &$Sechedule, $teacherId, $klassIds)
    {
        //定制查询当前教室的班级的条件
        $map['sechedule_id'] = $Sechedule->id;
        $flag = false;
        //找到当前教室的班级
        $currentSechedules = array();
        $currentSechedules = SecheduleKlass::where($map)->select();

        //判断教师在不在
        if (!($Sechedule->teacher_id === $teacherId))
        {

            if (!empty($currentSechedules))
            {
                return false;
            }

            //判断学生在不在
            foreach ($klassIds as $klassId)
            {

                foreach ($currentSechedules as $currentSechedule)
                {

                    if ($currentSechedule->klass_id === $klassId)
                    {

                        $flag = false;
                    }

                    //判断标记
                    if (!$flag)
                        return true;
                }
            }
        }

        //存在一样的老师或学生
        return true;
    }
}
