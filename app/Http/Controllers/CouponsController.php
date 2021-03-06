<?php
namespace App\Http\Controllers;
use App\User_coupon;
use Illuminate\Http\Request;
use App\Coupon;
use App\Store;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LaravelFCM\Facades\FCM;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Message\Topics;

class CouponsController extends Controller
{
    public function index()
    {
        $store = Store::all()->where('email', Auth::guard('store')->user()->email)->pluck('id');
        $coupons2 = Coupon::all()->where('Store_id', $store['0']);
        $count = DB::table('user_coupons')
            ->select('Coupon_id', DB::raw('SUM(use_status) as total '))
            ->where('Store_id',$store['0'])
            ->groupBy('Coupon_id')
            ->orderBy('Coupon_id', 'ASC')
            ->get();

        $ax = 0;
        foreach ($coupons2 as $item){
            $coupons[$ax] = $item;
            $ax++;
        }

        $zz = 0;
        foreach ($count as $aa) {
            $coupons[$zz]['count'] = $aa->total;
            $zz++;
        }
        return view('managment.coupon', compact('coupons'));
    }
    public function create()
    {
        return view('managment.couponcreate');
    }
    public function view($id)
    {
        $coupon = Coupon::all()->where('id', $id);
        $data = ['coupons' => $coupon];
        return view('managment.couponview', $data);
    }
    public function store(Request $request)
    {
        $messsages = array(
            'title.required' => '你必須輸入折價券名稱',
            'start.required' => '你必須輸入起始時間',
            'end.required' => '你必須輸入結束時間',
            'discount.required' => '你必須輸入折扣金額',
            'lowestprice.required' => '你必須輸入至少購物金額',
            'picture.required' => '你必須上傳圖片',
        );
        $rules = array(
            'title' => 'required',
            'start' => 'required',
            'end' => 'required',
            'discount' => 'required',
            'lowestprice' => 'required',
            'picture' => 'required',
        );
        $validator = Validator::make($request->all(), $rules, $messsages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }
        $store = Store::all()->where('email', Auth::guard('store')->user()->email)->pluck('id');
        if ($request->hasFile('picture')) {
            $file_name = $request->file('picture')->getClientOriginalName();
            $destinationPath = '/public/coupon';
            $request->file('picture')->storeAs($destinationPath, $file_name);
            // save new image $file_name to database
//            $coupon->update(['picture' => $file_name]);
            Coupon::create([
                'Store_id' => $store['0'],
                'title' => $request['title'],
                'start' => $request['start'],
                'end' => $request['end'],
                'discount' => $request['discount'],
                'lowestprice' => $request['lowestprice'],
                'picture' => $file_name,
            ]);
        }
        return redirect()->route('coulist');
    }
    public function edit($id)
    {
        $coupon = Coupon::all()->where('id', $id);
        $data = ['coupons' => $coupon];
        return view('managment.couponedit', $data);
    }
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);
        $coupon->update($request->all());
        if ($request->hasFile('picture')) {
            $file_name = $request->file('picture')->getClientOriginalName();
            $destinationPath = '/public/coupon';
            $request->file('picture')->storeAs($destinationPath, $file_name);
            // save new image $file_name to database
            $coupon->update(['picture' => $file_name]);
        }
        return redirect()->route('coulist');
    }
    public function destroy($id)
    {
        Coupon::destroy($id);
        return redirect()->route('coulist');
    }
    public function changestatus($id)
    {
        $coupon = Coupon::all()->where('id', $id)->first();
        if ($coupon['status'] == 0) {
            $coupon->update([
                'status' => 1
            ]);
        }
//            else{
//                $coupon->update([
//                    'status'=>0
//                ]);
//        }
        //send
        $user_id = User::all()->pluck('id');
        $store_id = Store::all()->where('email', Auth::guard('store')->user()->email)->pluck('id');
        $coupon_id = Coupon::where('id', $id)->pluck('id');
        foreach ($user_id as $user_id) {
            User_coupon::create([
                'User_id' => $user_id,
                'Store_id' => $store_id[0],
                'Coupon_id' => $coupon_id[0],
                'use_status' => '0'
            ]);
        }
        $this->push($coupon_id);
        return redirect()->route('coulist')->with('response', '已成功發送折價券 !');
    }
    public function push($coupon_id)
    {
        $coupon=Coupon::all()->where('id',$coupon_id->first());
        $notificationBuilder = new PayloadNotificationBuilder($coupon->first()->title);
        $notificationBuilder->setBody($coupon->first()->title)
            ->setSound('default');
        $notification = $notificationBuilder->build();
        $topic = new Topics();
        $topic->topic('news');
        $topicResponse = FCM::sendToTopic($topic, null, $notification, null);
        $topicResponse->isSuccess();
        $topicResponse->shouldRetry();
        $topicResponse->error();
        return null;
    }

}