<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use DB;
use App\Requisition;
//use mycustom\SMSRU;
use Meta;


class RequisitionController extends Controller
{
    //создание заявки
    public function store_req(Request $request){
        $data = $request->all();
        $name = $data["name"];
        $date_req = date('Y-m-d H:i', strtotime($data["date_req"]));
        $phone = $data["phone"];
        $region = $data["region"];
        $predoplata = $data["predoplata"];
        $postoplata = $data["postoplata"];
        $problem = $data["problem"];
        $requisition = DB::table('requisitions')->insert(['date_req'=>$date_req, 'name'=>$name, 'phone'=>$phone, 'is_postoplata'=> 0, 'predoplata'=>$predoplata, 'postoplata'=>$postoplata, 'region'=>$region, 'problem'=>$problem, 'is_payed'=>0]);
        if($requisition){
            //echo"Заявка создана";
            return redirect('home')->with('Success', 'Заявка создана!');
        }
    }
    //страница заявки
    public function edit_req($id){
		Meta::set('title', 'Заказ № '.$id.'');
        $reqs = DB::select('select * from `requisitions` where `id`=?', [$id]);
        $info = DB::select('select * from `static_info_table`');
        return view('get_req', ['reqs'=>$reqs, 'info'=>$info]);
    }
    //исполнитель забирает заявку
    public function gettingReq(Request $request){
        $data = $request->all();
        //$phone = $data["phone"];
        $code = $data["code"];
        $id = $data["id"];
        $result = DB::select('select * from `users` where `code`=?', [$code]);
        if($result){
            foreach($result as $value){
                $phone = $value->phone;
                $res_in_action = DB::select('select * from `reqs_in_action` where `phone`=?', [$phone]);
                if(!$res_in_action){
                    $res_req = DB::table('requisitions')->where('id', $id)->update(['in_action'=>1]);
                    $return_arr = array("can"=>1, "id"=>$id, "phone"=>$phone);
                    echo json_encode($return_arr);
                }
            }

        }
        else{
            echo 0;
        }
    }
    //получение уведомления от ЯндексДенег и отправка смс с номером телефона. Условие - предоплата
    public function receiveYM(Request $request){
        $secret_key = "******";
       
        // Генерация ключа, для проверки подлинности пришедших к нам данных
         $sha1 = sha1( $request['notification_type'] . '&'. $request['operation_id']. '&' . $request['amount'] . '&643&' . $request['datetime'] . '&'. $request['sender'] . '&' . $request['codepro'] . '&' . $secret_key. '&' . $request['label']);
        if ($sha1 == $request['sha1_hash'] ) {
            //$req_test = DB::table('static_info_table')->insert(['rules'=>$request['notification_type']]);
            $label = explode('|', $request['label']);
            $phone_user = $label[1];
            $res_in_action = DB::select('select * from `reqs_in_action` where `id`=?', [$request['comment']]);
            if($res_in_action){
                foreach ($res_in_action as $val){
                    $amount = $val->postoplata;
                    if($amount == $request['amount']){
                        $del = DB::table('reqs_in_action')->where('id', $label[0])->delete();
                    }
                }
            }
            else{
                $zakaz = DB::select('select * from `requisitions` where `id`=?', [$label[0]]);
                foreach($zakaz as $value){
                    $zakaz_phone = $value->phone;
                    $zakaz_name = $value->name;
                    $is_payed = $value->is_payed;
					$id = $value->id;
                    $message = $id." - ".$zakaz_name." - ".$zakaz_phone;
                    if($is_payed == 0){
                        //$body = file_get_contents("https://sms.ru/sms/send?api_id=7BF6EF1E-0CF2-745C-BA8B-EA2F77121B57&to=".$phone_user."&msg=".urlencode($message)."&json=1");
						$ch = curl_init("https://sms.ru/sms/send");
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_TIMEOUT, 30);
						curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
							"api_id" => "**********",
							"to" => $phone_user, // До 100 штук до раз
							"msg" => $message, // Если приходят крякозябры, то уберите iconv и оставьте только "Привет!",
							"json" => 1 // Для получения более развернутого ответа от сервера
						)));
						$body = curl_exec($ch);
						curl_close($ch);
                        $req = DB::table('requisitions')->where('id', $label[0])->update(['is_payed'=>1]);
                    }
                }
            }
        }
    }

    //исполнитель берет заявку по постоплате
    public function getreq_postoplata(Request $request){
        $label = explode('|', $request['label_post']);
        $id=$label[0];
        $phone_user = $label[1];
        $postoplata = $request['postoplata'];
        $r = DB::select('select * from `users` where `phone` =?', [$phone_user]);
        $zakaz = DB::select('select * from `requisitions` where `id`=?', [$id]);
        foreach($zakaz as $value){
            $zakaz_phone = $value->phone;
            $zakaz_name = $value->name;
            $zakaz_date = $value->date_req;
            $message = $id." - ".$zakaz_name." - ".$zakaz_phone;
            //$body = file_get_contents("https://sms.ru/sms/send?api_id=7BF6EF1E-0CF2-745C-BA8B-EA2F77121B57&to=".$phone_user."&msg=".urlencode($message)."&json=1");
			$ch = curl_init("https://sms.ru/sms/send");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
				"api_id" => "***********",
				"to" => $phone_user, // До 100 штук до раз
				"msg" => $message, // Если приходят крякозябры, то уберите iconv и оставьте только "Привет!",
				"json" => 1 // Для получения более развернутого ответа от сервера
			)));
			$body = curl_exec($ch);
			curl_close($ch);
            foreach($r as $v){
                $name_isp = $v->name;
            }
            $req = DB::table('reqs_in_action')->insert(['phone'=>$phone_user, 'req_id'=>$id, 'postoplata'=>$postoplata, 'datereq'=>$zakaz_date, 'name_isp'=> $name_isp]);
            if($req){return redirect('home');}
        }
    }
    //просмотр заявок по постоплате и их удаление
    public function viewPost(){
        $reqs = DB::select('select * from `reqs_in_action`');
        return view('viewPost', ['reqs'=>$reqs]);
    }
    public function clearBlock($id){
        $del = DB::table('reqs_in_action')->where('id', $id)->delete();
        if($del){return redirect('/view_postoplata')->with('Success', 'Исполнитель разблокирован!');}
    }
}
