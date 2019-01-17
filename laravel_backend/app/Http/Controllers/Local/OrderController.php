<?php

namespace App\Http\Controllers\Local;

use App\Models\PickUp;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Partner;
use Carbon\Carbon;
use App\Models\OrderGood;
use App\Models\OrderNote;
use Illuminate\Support\Facades\Config;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Route;
use App\Models\Courier;
use Illuminate\Support\Facades\Session;
use App\Models\History;
use phpDocumentor\Reflection\Types\Integer;
use App\Models\Role;

class OrderController extends Controller
{
    /*Возвращает страницу все заказов (АРХИВ)*/
    public function index(Request $request)
    {
        Session::put('redirect_back', $request->fullUrl());

        $orders_db = Order::when(isset($request->from_date) && $request->from_date, function ($query) use ($request) {
            return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $request->from_date)->toDateString());
        })->when(isset($request->to_date) && $request->to_date, function ($query) use ($request) {
            return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $request->to_date)->toDateString());
        })->when(isset($request->order_type) && $request->order_type != "", function ($query) use ($request) {
            return $query->where('type', $request->order_type);
        })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
            return $query->where('in_stock', $request->stock);
        })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
            return $query->where('correct_data', $request->correct);
        })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
            return $query->where('closed', $request->closed);
        })->when(isset($request->status) && $request->status, function ($query) use ($request) {
            return $query->where('status', $request->status);
        })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
            return $query->where('partner_id', $request->partner);
        })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->courier)->where('couriers.type', 1);
            });
        })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
            });
        })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
            return $query->where(function ($query) use ($request) {
                $query->whereHas('goods', function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                    ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                if (intval(trim($request->search))) {
                    $query->orWhere('orders.id', '=', intval(trim($request->search)));
                }
                return $query;
            });
        })->when(isset($request->sort) && $request->sort, function ($query) use ($request) {
            if ($request->sort == 'id') {
                $query->orderBy('id', $request->order);
            } elseif ($request->sort == 'partner') {
                $query->leftJoin('partners', 'orders.partner_id', '=', 'partners.id')->orderBy('partners.name', $request->order);
            } elseif ($request->sort == 'address') {
                $query->orderBy('address', $request->order);
            } elseif ($request->sort == 'time') {
                $query->orderBy('time', $request->order);
            } elseif ($request->sort == 'sum') {
                $query->orderBy('order_sum', $request->order);
            } elseif ($request->sort == 'shop') {
                $query->orderBy('to_shop', $request->order);
            } elseif ($request->sort == 'status') {
                $query->orderBy('status', $request->order);
            }
            return $query;
        })->when(!isset($request->sort) && !$request->sort, function ($query) {
            $query->orderBy('id', 'desc');
        })
            ->select('orders.*');

        $all_orders = $orders_db->get();
        $subtotal = $this->calculateSubtotal($all_orders);
        $orders = $orders_db->paginate(50);


        $partners = Partner::withTrashed()->get();

        $couriers = Courier::where('type', 1)->whereHas('orders', function ($query) use ($request) {
            $query->when(isset($request->from_date) && $request->from_date, function ($query) use ($request) {
                return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $request->from_date)->toDateString());
            })->when(isset($request->to_date) && $request->to_date, function ($query) use ($request) {
                return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $request->to_date)->toDateString());
            })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
                return $query->where('in_stock', $request->stock);
            })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
                return $query->where('correct_data', $request->correct);
            })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
                return $query->where('closed', $request->closed);
            })->when(isset($request->status) && $request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
                return $query->where('partner_id', $request->partner);
            })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $query->whereHas('goods', function ($query) use ($request) {
                        $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                    if (intval(trim($request->search))) {
                        $query->orWhere('orders.id', '=', intval(trim($request->search)));
                    }
                    return $query;
                });
            })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
                return $query->whereHas('couriers', function ($query) use ($request) {
                    $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
                });
            });
        })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
            $query->orWhere('couriers.id', $request->courier);
        })->get();

        $pick_ups = Courier::where('type', 2)->whereHas('orders', function ($query) use ($request) {
            $query->when(isset($request->from_date) && $request->from_date, function ($query) use ($request) {
                return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $request->from_date)->toDateString());
            })->when(isset($request->to_date) && $request->to_date, function ($query) use ($request) {
                return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $request->to_date)->toDateString());
            })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
                return $query->where('in_stock', $request->stock);
            })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
                return $query->where('correct_data', $request->correct);
            })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
                return $query->where('closed', $request->closed);
            })->when(isset($request->status) && $request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
                return $query->where('partner_id', $request->partner);
            })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $query->whereHas('goods', function ($query) use ($request) {
                        $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                    if (intval(trim($request->search))) {
                        $query->orWhere('orders.id', '=', intval(trim($request->search)));
                    }
                    return $query;
                });
            })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
                return $query->whereHas('couriers', function ($query) use ($request) {
                    $query->where('couriers.id', $request->courier)->where('couriers.type', 1);
                });
            });
        })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
            $query->orWhere('couriers.id', $request->pick_up);
        })->get();

        return view('local.orders.index')
            ->withOrders($orders)
            ->withSubtotal($subtotal)
            ->withStatuses(Config::get('order.statuses'))
            ->withPartners($partners)
            ->withCouriers($couriers)
            ->withPickUps($pick_ups);
    }

    /*Подсчет общих сумм на страницах товаров*/
    public function calculateSubtotal($orders)
    {

        $subtotal = array();

        if ($orders) {

            $subtotal['count'] = count($orders);
            $subtotal['all_summ'] = 0;
            $subtotal['commission'] = 0;
            $subtotal['to_shop'] = 0;

            foreach ($orders as $order) {
                if ($order->type == 1) {
                    if ($order->status == 5) {
                        $subtotal['all_summ'] += 0;
                        $subtotal['commission'] += 0;
                        $subtotal['to_shop'] += 0;
                    } elseif ($order->status == 6) {
                        $subtotal['all_summ'] += 0;
                        $subtotal['commission'] += 0;
                        $subtotal['to_shop'] += 0;
                    } elseif ($order->status == 7) {
                        $subtotal['all_summ'] += $order->order_sum;
                        $subtotal['commission'] += round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                        $subtotal['to_shop'] += $order->order_sum - round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                    } else {
                        $subtotal['all_summ'] += $order->order_sum;
                        $subtotal['commission'] += round($order->order_sum - $order->to_shop, 2);
                        $subtotal['to_shop'] += $order->to_shop;
                    }
                } elseif ($order->type == 2) {
                    $subtotal['all_summ'] += 0;
                    $subtotal['commission'] += $order->delivery_cost + $order->percent_sum + $order->extra_weight + $order->extra_volume + $order->additional_cost;
                    $subtotal['to_shop'] += $order->to_shop;
                }
            }
        } else {
            $subtotal['count'] = 0;
            $subtotal['all_summ'] = 0;
            $subtotal['commission'] = 0;
            $subtotal['to_shop'] = 0;
        }


        return $subtotal;
    }

    /*Возвращает страницу создания заказа*/
    public function create(Request $request)
    {
        $partners = Partner::orderBy('name')->select('id', 'name')->get();
        return view('local.orders.create')->withPartners($partners);
    }

    /*Сохранение нового заказа*/
    public function store(Request $request)
    {
        $validator = $this->storeValidation($request->all());

        $validator->after(function ($validator) use ($request) {

            if ($request->delivery_type == 1) {

                if ($request->order_type == 1) {

                    if (!isset($request->good_price) || trim($request->good_price) == "") {
                        $validator->errors()->add('good_price', 'Поле обязательно для заполнения.');
                    }
                    if (!isset($request->good_name) || trim($request->good_name) == "") {
                        $validator->errors()->add('good_name', 'Поле обязательно для заполнения.');
                    }

                } else {
                    if (!isset($request->good_name) || trim($request->good_name) == "") {
                        $validator->errors()->add('good_name', 'Поле обязательно для заполнения.');
                    }
                }

            } else {
                $exists = 0;
                $first = 1;

                if (isset($request->goods_price) && $request->goods_price && !empty($request->goods_price)) {

                    foreach ($request->goods_price as $key => $good_price) {
                        if (trim($good_price) != '' || trim($request->goods_name[$key]) != '') {
                            if (trim($good_price) != '' && trim($request->goods_name[$key]) == '') {
                                $validator->errors()->add('goods_name.' . $key, 'Поле обязательно для заполнения.');
                            }

                            if (trim($good_price) == '' && trim($request->goods_name[$key]) != '') {
                                $validator->errors()->add('goods_price.' . $key, 'Поле обязательно для заполнения.');
                            }
                        }
                        if (trim($good_price) != "" && trim($good_price) !== null && isset($request->goods_name[$key]) && trim($request->goods_name[$key]) != '') {
                            $exists = 1;
                        }
                        if ($first) {
                            $index = $key;
                            $first = 0;
                        }
                    }
                    if (!$exists) {
                        if (trim($request->goods_price[$index]) == "" || $request->goods_price[$index] == null) {
                            $validator->errors()->add('goods_price.' . $index, 'Поле обязательно для заполнения.');
                        }
                        if (trim($request->goods_name[$index]) == "" || $request->goods_name[$index] == null) {
                            $validator->errors()->add('goods_name.' . $index, 'Поле обязательно для заполнения.');
                        }
                    }
                } else {
                    $validator->errors()->add('goods_price.0', 'Поле обязательно для заполнения.');
                }
            }
            /*if (trim($request->weight) == "") {
                if (!isset($request->volume) || trim($request->volume) == '') {
                    $validator->errors()->add('volume', 'Поле обязательно для заполнения.');
                }
            } else {
                if (!isset($request->weight) || trim($request->weight) == '') {
                    $validator->errors()->add('weight', 'Поле обязательно для заполнения.');
                }
            }*/
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $order = new Order();
        $order->partner_id = $request->partner_id;
        $order->address = $request->address;
        $order->contact = $request->contact;
        $order->time = $request->time;
        $order->date = Carbon::createFromFormat('d/m/Y', $request->date)->toDateString();
        $order->delivery_type = $request->delivery_type;
        $order->weight = $request->weight;
        $order->volume = $request->volume;
        $order->additional_cost = $request->additional_cost;
        $order->type = $request->order_type;


        /*заказ доставка*/
        if ($request->order_type == 1) {


            $calculation = $this->calculate($request);

            $order->delivery_cost = $calculation['delivery_cost'];
            $order->percent_sum = $calculation['percent'];
            $order->extra_weight_cost = $calculation['extra_weight'];
            $order->extra_volume_cost = $calculation['extra_volume'];
            $order->order_sum = $calculation['sum'];
            $order->order_sum_max = $calculation['sum_max'];
            $order->to_shop = $calculation['to_shop'];
            $order->to_shop_max = $calculation['to_shop_max'];

            $partner = Partner::where('id', $request->partner_id)->first();

            if ($request->weight > $partner->weight_limit || $request->volume > $partner->volume_limit) {
                $order->heavy = 1;
            }

            $order->save();

            History::add($order->id, 'Заказ Добавлен', 'cat-add');

            if ($request->delivery_type == 1) {
                OrderGood::create([
                    'order_id' => $order->id,
                    'selected' => 1,
                    'name' => $request->good_name,
                    'price' => $request->good_price
                ]);
            } else {
                if ($request->goods_name) {
                    foreach ($request->goods_name as $key => $good_name) {
                        if ($good_name !== null) {
                            $orderGood = new OrderGood();
                            $orderGood->order_id = $order->id;
                            $orderGood->selected = isset($request->goods_check[$key]) ? 1 : 0;
                            $orderGood->name = $request->goods_name[$key];
                            $orderGood->price = $request->goods_price[$key];
                            $orderGood->save();
                        }
                    }
                }
            }

            /*заказ-забор*/
        } elseif ($request->order_type == 2) {

            $calculation = $this->calculatePickUp($request);

            $order->delivery_cost = $calculation['delivery_cost'];
            $order->percent_sum = $calculation['percent'];
            $order->extra_weight_cost = $calculation['extra_weight'];
            $order->extra_volume_cost = $calculation['extra_volume'];
            $order->order_sum = $calculation['sum'];
            $order->order_sum_max = $calculation['sum_max'];

            $order->to_shop = $calculation['to_shop'];
            $order->to_shop_max = $calculation['to_shop_max'];
            $order->heavy = 0;

            $order->save();

            History::add($order->id, 'Заказ Добавлен', 'cat-add');

            if ($request->delivery_type == 1) {
                OrderGood::create([
                    'order_id' => $order->id,
                    'selected' => 1,
                    'name' => $request->good_name,
                    'price' => 0
                ]);
            }

        }


        if (isset($request->note_text) && $request->note_text && !empty($request->note_text)) {
            foreach ($request->note_text as $key => $text) {
                OrderNote::create(['order_id' => $order->id, 'user_id' => Auth::id(), 'type' => $request->note_type[$key],
                    'text' => $text]);
                History::add($order->id, 'Примечание', 'cat-note');
            }
        }

        return redirect(route('show.order', ['id' => $order->id]))->with('status', 'Заказ был успешно создан');
    }

    /*Валидация при сохранении заказа*/
    public function storeValidation($data)
    {
        return Validator::make($data, [
            'partner_id' => 'required|integer|exists:partners,id',
            'address' => 'required|max:255',
            'contact' => 'required|max:255',
            'time' => 'required|max:255',
            'date' => 'required|regex:/\d{2}\/\d{2}\/\d{4}/',
            'delivery_type' => 'required|in:1,2',
            'good_price' => 'nullable|numeric',
            'goods_price.*' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'volume' => 'nullable|numeric',
            'additional_cost' => 'nullable|numeric',
            'note_text.*' => 'sometimes|required',
            'note_type.*' => 'sometimes|required|in:1,2,3',
            'order_type' => 'required|integer|in:1,2'
        ]);
    }

    /*Подсчет стоимостей заказа-доставки*/
    public function calculate(Request $request)
    {
        $return_arr = [
            'delivery_cost' => 0,
            'percent' => 0,
            'extra_weight' => 0,
            'extra_volume' => 0,
            'sum' => 0,
            'sum_max' => 0,
            'to_shop' => 0,
            'to_shop_max' => 0
        ];

        $partner = Partner::withTrashed()->where('id', $request->partner_id)->first();

        $return_arr['delivery_cost'] = $partner->delivery_cost;

        $goods_cost = 0;
        $goods_max_cost = 0;

        if ($request->delivery_type == 1) {

            $goods_cost = $request->good_price;
            $goods_max_cost = $request->good_price;

        } else {

            if (!empty($request->goods_price)) {

                foreach ($request->goods_price as $key => $price) {

                    if ($price !== null) {
                        if (isset($request->goods_check[$key])) {
                            $goods_cost += $price;
                        }
                        $goods_max_cost += $price;
                    }
                }
            }
        }

        $return_arr['sum'] = round($goods_cost, 2);
        $return_arr['sum_max'] = round($goods_max_cost, 2);

        if ($goods_cost != 0 && $partner->percent != 0) {
            $return_arr['percent'] = round(($goods_cost / 100) * $partner->percent, 2);
        }

        if ($request->weight) {
            if ($request->weight > $partner->weight_limit) {
                $return_arr['extra_weight'] = round(($request->weight - $partner->weight_limit) * $partner->extra_weight_cost, 2);
            }
        }

        if ($request->volume) {
            if ($request->volume > $partner->volume_limit) {
                $return_arr['extra_volume'] = round(($request->volume - $partner->volume_limit) * $partner->extra_volume_cost, 2);
            }
        }

        $return_arr['to_shop'] = round($return_arr['sum'] - $return_arr['delivery_cost'] - $return_arr['percent']
            - $return_arr['extra_weight'] - $return_arr['extra_volume'] - $request->additional_cost, 2);

        $return_arr['to_shop_max'] = round($return_arr['sum_max'] - $return_arr['delivery_cost'] - $return_arr['percent']
            - $return_arr['extra_weight'] - $return_arr['extra_volume'] - $request->additional_cost, 2);

        return $return_arr;
    }

    /*Подсчет стоимостей заказа-доставки*/
    public function ajaxCalculate(Request $request)
    {
        $validator = $this->calculateValidation($request->all());

        $validator->after(function ($validator) use ($request) {

            if ($request->delivery_type == 1) {
                if (!isset($request->good_price) || trim($request->good_price) == "") {
                    //$validator->errors()->add('good_price', 'Поле обязательно для заполнения.');
                    return ['success' => 0, 'errors' => []];
                }
            } else {

                $exists = 0;
                if (isset($request->goods_price) && $request->goods_price && !empty($request->goods_price)) {
                    foreach ($request->goods_price as $good_price) {
                        if (trim($good_price['price']) != "") {
                            $exists = 1;
                        }
                    }
                    if (!$exists) {
                        //$validator->errors()->add('goods_price.0.price', 'Поле обязательно для заполнения.');
                        return ['success' => 0, 'errors' => []];
                    }
                } else {
                    //$validator->errors()->add('goods_price.0.price', 'Поле обязательно для заполнения.');
                    return ['success' => 0, 'errors' => []];
                }
            }

            /*if (trim($request->weight) == "") {
                if (!isset($request->volume) || trim($request->volume) == '') {
                    $validator->errors()->add('volume', 'Поле обязательно для заполнения.');
                }
            } else {
                if (!isset($request->weight) || trim($request->weight) == '') {
                    $validator->errors()->add('weight', 'Поле обязательно для заполнения.');
                }
            }*/
        });

        if ($validator->fails()) {
            return ['success' => 0, 'errors' => $validator->messages()->getMessages()];
        }

        $return_arr = [
            'delivery_cost' => 0,
            'pick_up_cost' => 0,
            'percent' => 0,
            'extra_weight' => 0,
            'extra_volume' => 0,
            'sum' => 0,
            'sum_max' => 0,
            'to_shop' => 0,
            'to_shop_max' => 0
        ];

        $partner = Partner::withTrashed()->where('id', $request->partner_id)->first();

        if($request->order_type == '1'){
            $return_arr['delivery_cost'] = $partner->delivery_cost;
            $return_arr['pick_up_cost'] = $partner->pick_up_cost;

            $goods_cost = 0;
            $goods_max_cost = 0;

            if ($request->delivery_type == 1) {
                $goods_cost = $request->good_price;
                $goods_max_cost = $request->good_price;
            } else {
                if (!empty($request->goods_price)) {
                    foreach ($request->goods_price as $good) {
                        if ($good !== null) {
                            if ($good['checked'] == 1) {
                                $goods_cost += $good['price'];
                            }
                            $goods_max_cost += $good['price'];
                        }
                    }
                }
            }

            $return_arr['sum'] = round($goods_cost, 2);
            $return_arr['sum_max'] = round($goods_max_cost, 2);

            if ($goods_cost != 0 && $partner->percent != 0) {
                $return_arr['percent'] = round(($goods_cost / 100) * $partner->percent, 2);
            }

            if ($request->weight) {
                if ($request->weight > $partner->weight_limit) {
                    $return_arr['extra_weight'] = round(($request->weight - $partner->weight_limit) * $partner->extra_weight_cost, 2);
                }
            }

            if ($request->volume) {
                if ($request->volume > $partner->volume_limit) {
                    $return_arr['extra_volume'] = round(($request->volume - $partner->volume_limit) * $partner->extra_volume_cost, 2);
                }
            }

            $return_arr['to_shop'] = round($return_arr['sum'] - $return_arr['delivery_cost'] - $return_arr['percent']
                - $return_arr['extra_weight'] - $return_arr['extra_volume'] - $request->additional_cost, 2);

            $return_arr['to_shop_max'] = round($return_arr['sum_max'] - $return_arr['delivery_cost'] - $return_arr['percent']
                - $return_arr['extra_weight'] - $return_arr['extra_volume'] - $request->additional_cost, 2);

            return ['success' => 1, 'data' => $return_arr];
        }elseif($request->order_type == '2'){

            $return_arr['to_shop'] = 0 - ($partner->pick_up_cost + $request->additional_cost);

            return ['success' => 1, 'data' => $return_arr];
        }

    }

    /*Подсчет стоимостей заказа-забора*/
    public function calculatePickUp(Request $request)
    {
        $return_arr = [
            'delivery_cost' => 0,
            'percent' => 0,
            'extra_weight' => 0,
            'extra_volume' => 0,
            'sum' => 0,
            'sum_max' => 0,
            'to_shop' => 0,
            'to_shop_max' => 0
        ];

        $partner = Partner::withTrashed()->where('id', $request->partner_id)->first();

        $return_arr['delivery_cost'] = $partner->pick_up_cost;
        $return_arr['to_shop'] = 0 - ($partner->pick_up_cost + $request->additional_cost);

        return $return_arr;
    }

    /*Валидация подсчет стоимостей заказа-доставки*/
    public function calculateValidation($data)
    {
        return Validator::make($data, [
            'partner_id' => 'required|exists:partners,id',
            'delivery_type' => 'required|in:1,2',
            'good_price' => 'nullable|numeric',
            'goods_price.*.price' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'volume' => 'nullable|numeric',
            'additional_cost' => 'nullable|numeric'
        ]);
    }

    /*Возвращает поля для добавляения нового товара*/
    public function addGood(Request $request)
    {
        return view('local.orders.ajax.good')->withId($request->id);
    }

    /*Отображает страницу редактирования заказа*/
    public function show(Request $request)
    {
        $partners = Partner::orderBy('name')->select('id', 'name')->get();
        $order = Order::where('id', $request->id)->first();

        if (!$partners->where('id', $order->partner_id)->first()) {
            $partners[] = Partner::withTrashed()->where('id', $order->partner_id)->select('id', 'name')->first();
        }

        $order_goods = OrderGood::where('order_id', $request->id)->get();
        $order_notes = OrderNote::where('order_id', $request->id)
            ->where("type", "<>", 3)
            ->orWhere(function ($query) use ($request) {
                return $query->where("type", '=', 3)
                    ->where("user_id", "=", Auth::id())
                    ->where('order_id', '=', $request->id);
            })->get();

        $delivery_courier = $order->couriers()->where("type", 1)->first();

        $delivery_couriers = Courier::where('type', 1)
            ->when($delivery_courier, function ($query) use ($delivery_courier) {
                return $query->whereNotIn('id', [$delivery_courier->id]);
            })->get();

        $pick_up_courier = $order->couriers()->where('type', 2)->first();

        $pick_up_couriers = Courier::where('type', 2)
            ->when($pick_up_courier, function ($query) use ($pick_up_courier) {
                return $query->whereNotIn('id', [$pick_up_courier->id]);
            })->get();

        return view('local.orders.show')
            ->withOrder($order)
            ->withOrderGoods($order_goods)
            ->withPartners($partners)
            ->withOrderNotes($order_notes)
            ->withDeliveryCourier($delivery_courier)
            ->withDeliveryCouriers($delivery_couriers)
            ->withPickUpCourier($pick_up_courier)
            ->withPickUpCouriers($pick_up_couriers);
    }

    /*Редактирование заказа*/
    public function edit(Request $request)
    {
        $validator = $this->editValidation($request);

        $validator->after(function ($validator) use ($request) {
            if ($request->delivery_type == 1) {
                if (!isset($request->good_price) || trim($request->good_price) == "") {
                    $validator->errors()->add('good_price', 'Поле обязательно для заполнения.');
                }
                if (!isset($request->good_name) || trim($request->good_name) == "") {
                    $validator->errors()->add('good_name', 'Поле обязательно для заполнения.');
                }
            } else {
                $exists = 0;
                $first = 1;

                if (isset($request->goods_price) && $request->goods_price && !empty($request->goods_price)) {
                    foreach ($request->goods_price as $key => $good_price) {
                        if (trim($good_price) != '' || trim($request->goods_name[$key]) != '') {
                            if (trim($good_price) != '' && trim($request->goods_name[$key]) == '') {
                                $validator->errors()->add('goods_name.' . $key, 'Поле обязательно для заполнения.');
                            }

                            if (trim($good_price) == '' && trim($request->goods_name[$key]) != '') {
                                $validator->errors()->add('goods_price.' . $key, 'Поле обязательно для заполнения.');
                            }
                        }
                        if (trim($good_price) != "" && trim($good_price) !== null && isset($request->goods_name[$key]) && trim($request->goods_name[$key]) != '') {
                            $exists = 1;
                        }
                        if ($first) {
                            $index = $key;
                            $first = 0;
                        }
                    }
                    if (!$exists) {
                        if (trim($request->goods_price[$index]) == "" || $request->goods_price[$index] == null) {
                            $validator->errors()->add('goods_price.' . $index, 'Поле обязательно для заполнения.');
                        }
                        if (trim($request->goods_name[$index]) == "" || $request->goods_name[$index] == null) {
                            $validator->errors()->add('goods_name.' . $index, 'Поле обязательно для заполнения.');
                        }
                    }
                } else {
                    $validator->errors()->add('goods_price.0', 'Поле обязательно для заполнения.');
                }
            }
            /*if (trim($request->weight) == "") {
                if (!isset($request->volume) || trim($request->volume) == '') {
                    $validator->errors()->add('volume', 'Поле обязательно для заполнения.');
                }
            } else {
                if (!isset($request->weight) || trim($request->weight) == '') {
                    $validator->errors()->add('weight', 'Поле обязательно для заполнения.');
                }
            }*/
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $order = Order::find($request->id);

        if ($order->partner_id != $request->partner_id) {
            History::add($order->id, 'Изменение (Партнер: ' . Partner::find($order->partner_id)->name . ' - ' . Partner::find($request->partner_id)->name . ')', 'cat-change');
        }
        if ($order->address != $request->address) {
            History::add($order->id, 'Изменение (Адресс: ' . $order->address . ' - ' . $request->address . ')', 'cat-change');
        }
        if ($order->contact != $request->contact) {
            History::add($order->id, 'Изменение (Контакт: ' . $order->contact . ' - ' . $request->contact . ')', 'cat-change');
        }
        if ($order->time != $request->time) {
            History::add($order->id, 'Изменение (Время: ' . $order->time . ' - ' . $request->time . ')', 'cat-change');
        }
        if ($order->date != Carbon::createFromFormat('d/m/Y', $request->date)->toDateString()) {

            $courier_delivery = Courier::where('type', 1)->whereHas('orders', function ($query) use ($request) {
                $query->where('orders.id', $request->id);
            })->first();
            if ($courier_delivery) {
                $exists_delivery = Courier::where('id', $courier_delivery->id)->whereHas('routes', function ($query) use ($request) {
                    $query->where('date', Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
                })->first();
                if (!$exists_delivery) {
                    $courier_delivery->orders()->detach($order->id);
                    History::add($order->id, 'Доставка (' . ($courier_delivery->transport ? 'М | ' : 'П | ') . $courier_delivery->name . ' ' . $courier_delivery->phone . ' ' . $order->date . ' - \' \')', 'cat-change');
                }
            }

            $courier_pick_up = Courier::where('type', 2)->whereHas('orders', function ($query) use ($request) {
                $query->where('orders.id', $request->id);
            })->first();
            if ($courier_pick_up) {
                $exists_pick_up = Courier::where('id', $courier_pick_up->id)->whereHas('pick_ups', function ($query) use ($request) {
                    $query->where('date', Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
                })->first();
                if (!$exists_pick_up) {
                    $courier_pick_up->orders()->detach($order->id);
                    History::add($order->id, 'Забор (' . ($courier_pick_up->transport ? 'М | ' : 'П | ') . $courier_pick_up->name . ' ' . $courier_pick_up->phone . ' ' . $order->date . ' - \' \')', 'cat-change');
                }
            }

        }

        if ($order->date != Carbon::createFromFormat('d/m/Y', $request->date)->toDateString()) {
            History::add($order->id, 'Изменение (Дата: ' . Carbon::createFromFormat('Y-m-d', $order->date)->format('d/m/Y') . ' - ' . $request->date . ')', 'cat-change');
        }

        if ($order->weight != $request->weight) {
            History::add($order->id, 'Изменение (Вес: ' . (Float)$order->weight . ' - ' . (Float)$request->weight . ')', 'cat-change');
        }

        if ($order->volume != $request->volume) {
            History::add($order->id, 'Изменение (Объемный вес: ' . (Float)$order->volume . ' - ' . (Float)$request->volume . ')', 'cat-change');
        }

        if ($order->additional_cost != $request->additional_cost) {
            History::add($order->id, 'Изменение (Дополнительно: ' . (Float)$order->additional_cost . ' - ' . (Float)$request->additional_cost . ')', 'cat-change');
        }

        if ($order->delivery_type == 2 && $request->delivery_type == 1) {
            $order_goods = $order->goods()->get();
            History::add($order->id, 'Изменение (Тип доставки: ' . Config::get('order.delivery_types')[$order->delivery_type] . ' - ' . Config::get('order.delivery_types')[$request->delivery_type] . ')', 'cat-change');
            foreach ($order_goods as $good) {
                History::add($order->id, 'Удален товар (' . $good->name . ' цена: ' . $good->price . ')', 'cat-change');
            }
            History::add($order->id, 'Добавлен товар (' . $request->good_name . ' цена: ' . $request->good_price . ')', 'cat-change');
        } elseif ($order->delivery_type == 1 && $request->delivery_type == 2) {
            $order_good = $order->goods()->first();
            History::add($order->id, 'Изменение (Тип доставки: ' . Config::get('order.delivery_types')[$order->delivery_type] . ' - ' . Config::get('order.delivery_types')[$request->delivery_type] . ')', 'cat-change');
            History::add($order->id, 'Удален товар (' . $order_good->name . ' цена: ' . $order_good->price . ')', 'cat-change');

            foreach ($request->goods_name as $key => $good_name) {
                if ($good_name !== null) {
                    History::add($order->id, 'Добавлен товар (' . $request->goods_name[$key] . ' цена: ' . $request->goods_price[$key] . ')', 'cat-change');
                }

            }
        } elseif ($order->delivery_type == 1 && $request->delivery_type == 1) {
            $order_good = $order->goods()->first();
            if ($request->good_name != $order_good->name || $request->good_price != $order_good->price) {
                History::add($order->id, 'Изменен товар (' . $order_good->name . ' цена: ' . $order_good->price . ' - ' . $request->good_name . ' цена: ' . $request->good_price . ')', 'cat-change');
            }
        } elseif ($order->delivery_type == 2 && $request->delivery_type == 2) {
            $order_goods = $order->goods()->get();

            foreach ($order_goods as $good) {
                if (isset($request->goods_name[$good->id]) && $request->goods_name[$good->id] != null) {
                    if ($request->goods_name[$good->id] != $good->name || $request->goods_price[$good->id] != $good->price) {
                        History::add($order->id, 'Изменен товар (' . $good->name . ' цена: ' . $good->price . ' - ' . $request->goods_name[$good->id] . ' цена: ' . $request->goods_price[$good->id] . ')', 'cat-change');
                    }
                    if ($good->selected && !isset($request->goods_check[$good->id])) {
                        History::add($order->id, 'Товар перестал быть выбранным (' . $request->goods_name[$good->id] . ' цена: ' . $request->goods_price[$good->id] . ')', 'cat-change');
                    }
                    if (!$good->selected && isset($request->goods_check[$good->id])) {
                        History::add($order->id, 'Товар стал выбранным (' . $request->goods_name[$good->id] . ' цена: ' . $request->goods_price[$good->id] . ')', 'cat-change');
                    }
                } else {
                    History::add($order->id, 'Удален товар (' . $good->name . ' цена: ' . $good->price . ')', 'cat-change');
                }
            }

            $order_goods_id = $order_goods->pluck('id')->toArray();

            foreach ($request->goods_name as $key => $good_name) {
                if ($good_name !== null) {
                    if (!in_array($key, $order_goods_id)) {
                        History::add($order->id, 'Добавлен товар (' . $request->goods_name[$key] . ' цена: ' . $request->goods_price[$key] . ')', 'cat-change');
                    }
                }
            }
        }

        $order->partner_id = $request->partner_id;
        $order->address = $request->address;
        $order->contact = $request->contact;
        $order->time = $request->time;
        $order->date = Carbon::createFromFormat('d/m/Y', $request->date)->toDateString();
        $order->delivery_type = $request->delivery_type;
        $order->weight = $request->weight;
        $order->volume = $request->volume;
        $order->additional_cost = $request->additional_cost;

        if ($order->type == 1) {
            $calculation = $this->calculate($request);
        } else {
            $calculation = $this->calculatePickUp($request);
        }

        $order->delivery_cost = $calculation['delivery_cost'];
        $order->percent_sum = $calculation['percent'];
        $order->extra_weight_cost = $calculation['extra_weight'];
        $order->extra_volume_cost = $calculation['extra_volume'];
        $order->order_sum = $calculation['sum'];
        $order->order_sum_max = $calculation['sum_max'];
        $order->to_shop = $calculation['to_shop'];
        $order->to_shop_max = $calculation['to_shop_max'];
        $partner = Partner::withTrashed()->where('id', $request->partner_id)->first();
        if ($request->weight > $partner->weight_limit || $request->volume > $partner->volume_limit) {
            $order->heavy = 1;
        }

        $order->save();

        $order->goods()->delete();

        if ($request->delivery_type == 1) {
            OrderGood::create([
                'order_id' => $order->id,
                'selected' => 1,
                'name' => $request->good_name,
                'price' => $request->good_price,
                'partner_id' => $order->partner_id
            ]);
        } else {
            if ($request->goods_name) {
                foreach ($request->goods_name as $key => $good_name) {
                    if ($good_name !== null) {
                        $orderGood = new OrderGood();
                        $orderGood->order_id = $order->id;
                        $orderGood->selected = isset($request->goods_check[$key]) ? 1 : 0;
                        $orderGood->name = $request->goods_name[$key];
                        $orderGood->price = $request->goods_price[$key];
                        $orderGood->save();
                    }
                }
            }
        }
        if (Session::has('redirect_back')) {
            return redirect(Session::get('redirect_back'))->with('status', 'Заказ был успешно изменен');
        }
        return redirect(route('current.orders'))->with('status', 'Заказ был успешно изменен');
    }

    public function editValidation($request)
    {
        return Validator::make($request->all(), [
            'order_id' => 'required|in:' . $request->id . '|exists:orders,id,closed,0',
            'partner_id' => 'required|integer|exists:partners,id',
            'address' => 'required|max:255',
            'contact' => 'required|max:255',
            'time' => 'required|max:255',
            'date' => 'required|regex:/\d{2}\/\d{2}\/\d{4}/',
            'delivery_type' => 'required|in:1,2',
            'good_price' => 'nullable|numeric',
            'good_name' => 'nullable|max:255',
            'goods_price.*' => 'nullable|numeric',
            'goods_name.*' => 'nullable|max:255',
            'weight' => 'nullable|numeric',
            'volume' => 'nullable|numeric',
            'additional_cost' => 'nullable|numeric'
        ]);
    }

    /*Изминение статуса Данные не верны/Данные верны*/
    public function changeCorrect(Request $request)
    {
        $validator = $this->changeCheckboxValidation($request->all());
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        $order = Order::find($request->order_id);
        $order->correct_data = $request->checked;
        $order->save();
        if ($request->checked) {
            History::add($order->id, 'Данные верны', 'cat-correct');
        } else {
            History::add($order->id, 'Данные не верны', 'cat-correct');
        }
        return response()->json(['success' => 1]);
    }

    /*Изминения статуса заказа "На складе/Не на складе"*/
    public function changeStock(Request $request)
    {
        $validator = $this->changeCheckboxValidation($request->all());
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        $order = Order::find($request->order_id);
        $order->in_stock = $request->checked;
        $order->save();
        if ($request->checked) {
            History::add($order->id, 'На складе', 'cat-at-storage');
        } else {
            History::add($order->id, 'Не на складе', 'cat-at-storage');
        }
        return response()->json(['success' => 1]);
    }

    /*валидатор*/
    public function changeCheckboxValidation($data)
    {
        return Validator::make($data, [
            'order_id' => 'required|exists:orders,id,closed,0',
            'checked' => 'required|in:0,1'
        ]);
    }

    /*Изминения общего статуса заказа*/
    public function changeStatus(Request $request)
    {
        $validator = $this->changeStatusValidation($request->all());
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        $order = Order::find($request->order_id);
        History::add($order->id, 'Статус (' . Config::get('order.statuses')[$order->status] . ' - ' . Config::get('order.statuses')[$request->status] . ')', 'cat-status');
        $order->status = $request->status;
        $order->save();

        if ($request->list === false) {
            return response()->json(['success' => 1]);
        } else {
            $order_sum = 0;
            $delivery = 0;
            $to_shop = 0;

            parse_str($request->form, $form);
            if ($order->status == 6) {
                $order_sum = 0;
                $delivery = 0;
                $to_shop = 0;
            } elseif ($order->status == 7) {
                $order_sum = $order->order_sum;
                $delivery = round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                $to_shop = $order->order_sum - round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
            } elseif ($order->status == 5) {
                $order_sum = 0;
                $delivery = 0;
                $to_shop = 0;
            } else {
                $order_sum = $order->order_sum;
                $delivery = round($order->order_sum - $order->to_shop, 2);
                $to_shop = $order->to_shop;
            }

            $all_orders = Order::when(isset($form['date']) && $form['date'] != "" && $request->page_type == "current", function ($query) use ($form) {
                return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $form['date'])->toDateString());
            })->when(isset($form['date']) && $form['date'] == "" && $request->page_type == "current", function ($query) use ($form) {
                return $query->where('date', "=", Carbon::now()->toDateString());
            })->when(isset($form['from_date']) && $form['from_date'] != "" && $request->page_type == "index", function ($query) use ($form) {
                return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $form['from_date'])->toDateString());
            })->when(isset($form['to_date']) && $form['to_date'] && $request->page_type == "index", function ($query) use ($form) {
                return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $form['to_date'])->toDateString());
            })->when(isset($form['order_type']) && $form['order_type'] != "", function ($query) use ($form) {
                return $query->where('type', $form['order_type']);
            })->when(isset($form['stock']) && ($form['stock'] || $form['stock'] == 0) && $form['stock'] != "", function ($query) use ($form) {
                return $query->where('in_stock', $form['stock']);
            })->when(isset($form['correct']) && ($form['correct'] || $form['correct'] == 0) && $form['correct'] != "", function ($query) use ($form) {
                return $query->where('correct_data', $form['correct']);
            })->when(isset($form['closed']) && ($form['closed'] || $form['closed'] == 0) && $form['closed'] != "", function ($query) use ($form) {
                return $query->where('closed', $form['closed']);
            })->when(isset($form['status']) && $form['status'] != "", function ($query) use ($form) {
                return $query->where('status', $form['status']);
            })->when(isset($form['partner']) && $form['partner'] != "", function ($query) use ($form) {
                return $query->where('partner_id', $form['partner']);
            })->when(isset($form['courier']) && $form['courier'] != "", function ($query) use ($form) {
                return $query->whereHas('couriers', function ($query) use ($form) {
                    $query->where('couriers.id', $form['courier']);
                });
            })->when(isset($form['pick_up']) && $form['pick_up'] != "", function ($query) use ($form) {
                return $query->whereHas('couriers', function ($query) use ($form) {
                    $query->where('couriers.id', $form['pick_up'])->where('couriers.type', 2);
                });
            })->when(isset($form['search']) && $form['search'] && $form['search'] != '', function ($query) use ($form) {
                return $query->where(function ($query) use ($form) {
                    $query->whereHas('goods', function ($query) use ($form) {
                        $query->where('name', 'ilike', '%' . trim($form['search']) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($form['search']) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($form['search']) . '%');

                    if (intval(trim($form['search']))) {
                        $query->orWhere('orders.id', '=', intval(trim($form['search'])));
                    }
                    return $query;
                });
            })->select('orders.*')->get();

            $subtotal = $this->calculateSubtotal($all_orders);

            return response()->json(['success' => 1, 'order_sum' => $order_sum, 'delivery' => $delivery,
                'to_shop' => $to_shop, 'subtotal' => $subtotal]);
        }
    }

    /*валидатор*/
    public function changeStatusValidation($data)
    {
        return Validator::make($data, [
            'order_id' => 'required|exists:orders,id,closed,0',
            'status' => 'required|in:' . implode(',', array_keys(config('order.statuses')))
        ]);
    }

    /*Изминяет статус заказа "закрыт/Не закрыт*/
    public function changeClosed(Request $request)
    {
        $validator = $this->changeClosedValidation($request->all());
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }
        if (Role::inRole('tech') || Role::inRole('super')) {
            $order = Order::find($request->order_id);
            $order->closed = $request->checked;
            $order->save();
            if ($request->checked) {
                History::add($order->id, 'Закрыт', 'cat-closed');
            } else {
                History::add($order->id, 'Не закрыт', 'cat-closed');
            }
            return response()->json(['success' => 1]);
        }
        return response()->json(['success' => 0]);
    }

    /*валидатор*/
    public function changeClosedValidation($data)
    {
        return Validator::make($data, [
            'order_id' => 'required|exists:orders,id',
            'checked' => 'required|in:0,1'
        ]);
    }

    /*Удаляет заказ*/
    public function remove(Request $request)
    {
        $validator = $this->removeValidation($request);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->first('remove_order_id'));
        }

        $order = Order::find($request->id);
        $order->goods()->delete();
        $order->notes()->delete();
        $order->couriers()->detach();
        History::add($order->id, 'Удаление заказа', 'cat-change');
        $order->delete();
        if (Session::has('redirect_back')) {
            return redirect(Session::get('redirect_back'));
        }
        return redirect()->route('index.orders');
    }

    /*валидатор*/
    public function removeValidation($request)
    {
        return Validator::make($request->all(), [
            'remove_order_id' => 'required|exists:orders,id,closed,0|in:' . $request->id
        ]);
    }

    /*Живой поиск*/
    public function liveSearch(Request $request)
    {
        $orders = Order::when(isset($request->from_date) && $request->from_date && $request->from_date != '' && $request->page_type == 'index', function ($query) use ($request) {
            return $query->where('orders.date', ">=", Carbon::createFromFormat('d/m/Y', $request->from_date)->toDateString());
        })->when(isset($request->to_date) && $request->to_date && $request->to_date != '' && $request->page_type == 'index', function ($query) use ($request) {
            return $query->where('orders.date', "<=", Carbon::createFromFormat('d/m/Y', $request->to_date)->toDateString());
        })->when(isset($request->date) && $request->date && $request->date != '' && $request->page_type == 'current', function ($query) use ($request) {
            return $query->where('orders.date', "=", Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
        })->when(!isset($request->date) && (!$request->date || $request->date == '') && $request->page_type == 'current', function ($query) use ($request) {
            return $query->where('orders.date', "=", Carbon::now()->toDateString());
        })->when(isset($request->stock) && ($request->stock || $request->stock == 0) && $request->stock != '', function ($query) use ($request) {
            return $query->where('orders.in_stock', $request->stock);
        })->when(isset($request->correct) && ($request->correct || $request->correct == 0) && $request->correct != '', function ($query) use ($request) {
            return $query->where('orders.correct_data', $request->correct);
        })->when(isset($request->status) && $request->status && $request->status != '', function ($query) use ($request) {
            return $query->where('orders.status', $request->status);
        })->when(isset($request->partner) && $request->partner && $request->partner != '', function ($query) use ($request) {
            return $query->where('orders.partner_id', $request->partner);
        })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
            return $query->where(function ($query) use ($request) {

                $query->whereHas('goods', function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                })
                    ->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                    ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                if (intval(trim($request->search))) {
                    $query->orWhere('orders.id', '=', intval(trim($request->search)));
                }

                return $query;
            });

        })->orderBy('id', 'asc')->skip(0)->take(10)->get();


        return view('local.orders.ajax.live_search')
            ->withOrders($orders)
            ->withStatuses(Config::get('order.statuses'));

    }

    /*Отображение страницу текущих заказов*/
    public function current(Request $request)
    {
        Session::put('redirect_back', $request->fullUrl());

        $orders_db = Order::when(isset($request->date) && $request->date != "", function ($query) use ($request) {
            return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
        })->when(!isset($request->date) || $request->date == "", function ($query) use ($request) {
            return $query->where('date', "=", Carbon::now()->toDateString());
        })->when(isset($request->order_type) && $request->order_type != "", function ($query) use ($request) {
            return $query->where('type', $request->order_type);
        })->when(isset($request->stock) && ($request->stock != "" || $request->stock == 0), function ($query) use ($request) {
            return $query->where('in_stock', $request->stock);
        })->when(isset($request->correct) && ($request->correct != "" || $request->correct == 0), function ($query) use ($request) {
            return $query->where('correct_data', $request->correct);
        })->when(isset($request->closed) && ($request->closed != "" || $request->closed == 0), function ($query) use ($request) {
            return $query->where('closed', $request->closed);
        })->when(isset($request->status) && $request->status != "", function ($query) use ($request) {
            return $query->where('status', $request->status);
        })->when(isset($request->partner) && $request->partner != "", function ($query) use ($request) {
            return $query->where('partner_id', $request->partner);

        })->when(isset($request->courier) && $request->courier != "", function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->courier)->where('couriers.type', 1);
            });

        })->when(isset($request->pick_up) && $request->pick_up != "", function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
            });

        })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
            return $query->where(function ($query) use ($request) {
                $query->whereHas('goods', function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                    ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');
                if (intval(trim($request->search))) {
                    $query->orWhere('orders.id', '=', intval(trim($request->search)));
                }
                return $query;
            });

        })->when(isset($request->sort) && $request->sort, function ($query) use ($request) {
            if ($request->sort == 'id') {
                $query->orderBy('id', $request->order);
            } elseif ($request->sort == 'partner') {
                $query->leftJoin('partners', 'orders.partner_id', '=', 'partners.id')->orderBy('partners.name', $request->order);
            } elseif ($request->sort == 'address') {
                $query->orderBy('address', $request->order);
            } elseif ($request->sort == 'time') {
                $query->orderBy('time', $request->order);
            } elseif ($request->sort == 'sum') {
                $query->orderBy('order_sum', $request->order);
            } elseif ($request->sort == 'shop') {
                $query->orderBy('to_shop', $request->order);
            } elseif ($request->sort == 'status') {
                $query->orderBy('status', $request->order);
            }
            return $query;

        })->when(!isset($request->sort) && !$request->sort, function ($query) {

            $query->orderBy('id', 'desc');

        })
            ->select('orders.*');

        /*echo "<div style='display: none;visibility: hidden;'>";
        print_r($orders_db);
        echo "</div>";*/

        $all_orders = $orders_db->get();
        $subtotal = $this->calculateSubtotal($all_orders);
        $orders = $orders_db->get();

        $couriers = Courier::where('type', 1)->whereHas('orders', function ($query) use ($request) {

            $query->when(isset($request->date) && $request->date, function ($query) use ($request) {
                return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
            })->when(!isset($request->date) && !$request->date, function ($query) use ($request) {
                return $query->where('date', "=", Carbon::now()->toDateString());
            })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
                return $query->where('in_stock', $request->stock);
            })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
                return $query->where('correct_data', $request->correct);
            })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
                return $query->where('closed', $request->closed);
            })->when(isset($request->status) && $request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
                return $query->where('partner_id', $request->partner);
            })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {

                return $query->where(function ($query) use ($request) {
                    $query->whereHas('goods', function ($query) use ($request) {
                        $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');
                    if (intval(trim($request->search))) {
                        $query->orWhere('orders.id', '=', intval(trim($request->search)));
                    }
                    return $query;
                });

            })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
                return $query->whereHas('couriers', function ($query) use ($request) {
                    $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
                });
            });

        })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
            $query->orWhere('couriers.id', $request->courier);
        })->get();

        $pick_ups = Courier::where('type', 2)->whereHas('orders', function ($query) use ($request) {

            $query->when(isset($request->date) && $request->date, function ($query) use ($request) {
                return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
            })->when(!isset($request->date) && !$request->date, function ($query) use ($request) {
                return $query->where('date', "=", Carbon::now()->toDateString());
            })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
                return $query->where('in_stock', $request->stock);
            })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
                return $query->where('correct_data', $request->correct);
            })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
                return $query->where('closed', $request->closed);
            })->when(isset($request->status) && $request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
                return $query->where('partner_id', $request->partner);
            })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $query->whereHas('goods', function ($query) use ($request) {
                        $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                    if (intval(trim($request->search))) {
                        $query->orWhere('orders.id', '=', intval(trim($request->search)));
                    }
                    return $query;
                });
            })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
                return $query->whereHas('couriers', function ($query) use ($request) {
                    $query->where('couriers.id', $request->courier)->where('couriers.type', 1);
                });
            });
        })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
            $query->orWhere('couriers.id', $request->pick_up);
        })->get();

        $partners = Partner::withTrashed()->get();

        return view('local.orders.current')
            ->withSubtotal($subtotal)
            ->withOrders($orders)
            ->withStatuses(Config::get('order.statuses'))
            ->withPartners($partners)
            ->withCouriers($couriers)
            ->withPickUps($pick_ups);
    }

    /*Создание файла-експорта для архивных заказов*/
    public function indexReport(Request $request)
    {
        $orders = Order::when(isset($request->from_date) && $request->from_date, function ($query) use ($request) {
            return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $request->from_date)->toDateString());
        })->when(isset($request->to_date) && $request->to_date, function ($query) use ($request) {
            return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $request->to_date)->toDateString());
        })->when(isset($request->order_type) && $request->order_type != "", function ($query) use ($request) {
            return $query->where('type', $request->order_type);
        })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
            return $query->where('in_stock', $request->stock);
        })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
            return $query->where('correct_data', $request->correct);
        })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
            return $query->where('closed', $request->closed);
        })->when(isset($request->status) && $request->status, function ($query) use ($request) {
            return $query->where('status', $request->status);
        })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
            return $query->where('partner_id', $request->partner);
        })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->courier);
            });
        })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
            });
        })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
            return $query->where(function ($query) use ($request) {
                $query->whereHas('goods', function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                    ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');

                if (intval(trim($request->search))) {
                    $query->orWhere('orders.id', '=', intval(trim($request->search)));
                }
                return $query;
            });
        })->when(isset($request->sort) && $request->sort, function ($query) use ($request) {
            if ($request->sort == 'id') {
                $query->orderBy('id', $request->order);
            } elseif ($request->sort == 'partner') {
                $query->leftJoin('partners', 'orders.partner_id', '=', 'partners.id')->orderBy('partners.name', $request->order);
            } elseif ($request->sort == 'address') {
                $query->orderBy('address', $request->order);
            } elseif ($request->sort == 'time') {
                $query->orderBy('time', $request->order);
            } elseif ($request->sort == 'sum') {
                $query->orderBy('order_sum', $request->order);
            } elseif ($request->sort == 'shop') {
                $query->orderBy('to_shop', $request->order);
            } elseif ($request->sort == 'status') {
                $query->orderBy('status', $request->order);
            }
            return $query;
        })->when(!isset($request->sort) && !$request->sort, function ($query) {
            $query->orderBy('id', 'desc');
        })
            ->select('orders.*')
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->back()->with('error', 'Нечего выгружать');
        }

        $filename = "Все заказы ";

        if (isset($request->partner) && $request->partner != "") {
            $filename .= Partner::where('id', $request->partner)->first()->name;
        }
        if (isset($request->from_date) && $request->from_date && isset($request->to_date) && $request->to_date) {
            $filename .= " " . $request->from_date . " - " . $request->to_date;
        } elseif (isset($request->from_date) && $request->from_date && (!isset($request->to_date) || $request->to_date == "")) {
            $filename .= " от " . $request->from_date;
        } elseif ((!isset($request->from_date) || $request->from_date == "") && (isset($request->to_date) && $request->to_date)) {
            $filename .= " до  " . $request->to_date;
        }

        Excel::create($filename, function ($excel) use ($orders) {
            // Set the title
            $excel->setTitle('Отчет по заказам');

            // Chain the setters
            $excel->setCreator('I-Dostavka')
                ->setCompany('I-Dostavka');

            // Call them separately
            $excel->setDescription('I-Dostavka');

            $excel->sheet('Отчет по заказам', function ($sheet) use ($orders) {
                $i = 2;
                $sheet->appendRow(['Дата', "Магазин", "Адрес", "Товар", "Сумма", "Контакт", "Время", "Доставка", "Остаток"]);

                $sheet->cell('A1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('B1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('C1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('D1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('E1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('F1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('G1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('H1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('I1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });

                $sheet->cells('A1:I1', function ($cells) {
                    $cells->setBackground('#23395b');
                });

                $styleArray = array(
                    'font' => array(
                        'bold' => true,
                        'color' => array('rgb' => 'FFFFFF'),
                        'size' => 14
                    ));

                $sheet->getStyle('A1:I1')->applyFromArray($styleArray);

                foreach ($orders as $order) {
                    if ($order->type === 1) {

                        if ($order->status == 5) {
                            $delivery = 0;
                            $to_shop = 0;
                        } elseif ($order->status == 6) {
                            $delivery = 0;
                            $to_shop = 0;
                        } elseif ($order->status == 7) {
                            $delivery = round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                            $to_shop = $order->order_sum - round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) - $order->additional_cost;
                        } else {
                            $delivery = round($order->order_sum - $order->to_shop, 2);
                            $to_shop = $order->to_shop;
                        }

                        if ($order->goods()->count() > 1) {
                            for ($j = 0; $j < $order->goods()->count(); $j++) {
                                if ($j == 0) {
                                    $sheet->appendRow([$order->date,
                                        $order->partner->name,
                                        $order->address,
                                        $order->goods()->get()[$j]->selected ? $order->goods()->get()[$j]->name . " (выбран)" : $order->goods()->get()[$j]->name,
                                        $order->goods()->get()[$j]->price,
                                        $order->contact,
                                        $order->time,
                                        $delivery,
                                        $to_shop]);
                                } else {
                                    $sheet->appendRow([
                                        '',
                                        '',
                                        '',
                                        $order->goods()->get()[$j]->selected ? $order->goods()->get()[$j]->name . " (выбран)" : $order->goods()->get()[$j]->name,
                                        $order->goods()->get()[$j]->price,
                                        '',
                                        '',
                                        '',
                                        ''
                                    ]);
                                }
                            }

                            $sheet->setMergeColumn([
                                'columns' => ['A', 'B', 'C', 'F', 'G', 'H', 'I'],
                                'rows' => [[$i, $i + ($order->goods()->count() - 1)]],
                            ]);

                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('D' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            for ($a = $i; $a < $i + $order->goods()->count(); $a++) {
                                $sheet->cell('E' . $a, function ($cell) {
                                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                                });
                            }
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            for ($a = $i; $a < $i + $order->goods()->count(); $a++) {
                                $sheet->cells("A".$a.":I".$a, function($cells) {
                                    $cells->setBorder('thin', 'thin', 'thin', 'thin');
                                });
                            }

                            $i += $order->goods()->count();
                        } elseif ($order->goods()->count() == 1) {
                            $sheet->appendRow([$order->date,
                                $order->partner->name,
                                $order->address,
                                $order->goods()->first()->name,
                                $order->order_sum,
                                $order->contact,
                                $order->time,
                                $delivery,
                                $to_shop]);

                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('D' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('E' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cells("A".$i.":I".$i, function($cells) {
                                $cells->setBorder('thin', 'thin', 'thin', 'thin');
                            });

                            $i++;
                        }
                    } elseif ($order->type == 2) {
                        $sheet->appendRow([
                            $order->date,
                            $order->partner->name,
                            $order->address,
                            $order->goods()->first()->name,
                            'zabor',
                            $order->contact,
                            $order->time,
                            $order->delivery_cost,
                            $order->to_shop
                        ]);

                        $sheet->cell('A' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('A' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('B' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('B' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('C' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('D' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('E' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('F' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('F' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('G' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('G' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('H' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('H' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('I' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('I' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cells("A".$i.":I".$i, function($cells) {
                            $cells->setBorder('thin', 'thin', 'thin', 'thin');
                        });

                        $i++;
                    }
                }
            });

        })->download('xls');
    }

    /*Создание файла експорта для текущих заказов*/
    public function currentReport(Request $request)
    {
        $orders = Order::when(isset($request->date) && $request->date, function ($query) use ($request) {
            return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $request->date)->toDateString());
        })->when(!isset($request->date) && !$request->date, function ($query) use ($request) {
            return $query->where('date', "=", Carbon::now()->toDateString());
        })->when(isset($request->order_type) && $request->order_type != "", function ($query) use ($request) {
            return $query->where('type', $request->order_type);
        })->when(isset($request->stock) && ($request->stock || $request->stock == 0), function ($query) use ($request) {
            return $query->where('in_stock', $request->stock);
        })->when(isset($request->correct) && ($request->correct || $request->correct == 0), function ($query) use ($request) {
            return $query->where('correct_data', $request->correct);
        })->when(isset($request->closed) && ($request->closed || $request->closed == 0), function ($query) use ($request) {
            return $query->where('closed', $request->closed);
        })->when(isset($request->status) && $request->status, function ($query) use ($request) {
            return $query->where('status', $request->status);
        })->when(isset($request->partner) && $request->partner, function ($query) use ($request) {
            return $query->where('partner_id', $request->partner);
        })->when(isset($request->courier) && $request->courier, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->courier);
            });
        })->when(isset($request->pick_up) && $request->pick_up, function ($query) use ($request) {
            return $query->whereHas('couriers', function ($query) use ($request) {
                $query->where('couriers.id', $request->pick_up)->where('couriers.type', 2);
            });
        })->when(isset($request->search) && $request->search && $request->search != '', function ($query) use ($request) {
            return $query->where(function ($query) use ($request) {
                $query->whereHas('goods', function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . trim($request->search) . '%');
                })->orWhere('address', 'ilike', '%' . trim($request->search) . '%')
                    ->orWhere('contact', 'ilike', '%' . trim($request->search) . '%');
                if (intval(trim($request->search))) {
                    $query->orWhere('orders.id', '=', intval(trim($request->search)));
                }
                return $query;
            });
        })->when(isset($request->sort) && $request->sort, function ($query) use ($request) {
            if ($request->sort == 'id') {
                $query->orderBy('id', $request->order);
            } elseif ($request->sort == 'partner') {
                $query->leftJoin('partners', 'orders.partner_id', '=', 'partners.id')->orderBy('partners.name', $request->order);
            } elseif ($request->sort == 'address') {
                $query->orderBy('address', $request->order);
            } elseif ($request->sort == 'time') {
                $query->orderBy('time', $request->order);
            } elseif ($request->sort == 'sum') {
                $query->orderBy('order_sum', $request->order);
            } elseif ($request->sort == 'shop') {
                $query->orderBy('to_shop', $request->order);
            } elseif ($request->sort == 'status') {
                $query->orderBy('status', $request->order);
            }
            return $query;
        })->when(!isset($request->sort) && !$request->sort, function ($query) {
            $query->orderBy('id', 'desc');
        })
            ->select('orders.*')
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->back()->with('error', 'Нечего выгружать');
        }

        $filename = "Заказы";

        if (isset($request->partner) && $request->partner != "") {
            $filename .= " " . Partner::where('id', $request->partner)->first()->name;
        }

        if (isset($request->date) && $request->date) {
            $filename .= " " . $request->date;
        } else {
            $filename .= " " . Carbon::now()->toDateString();
        }


        Excel::create($filename, function ($excel) use ($orders) {
            // Set the title
            $excel->setTitle('Отчет по заказам');

            // Chain the setters
            $excel->setCreator('I-Dostavka')
                ->setCompany('I-Dostavka');

            // Call them separately
            $excel->setDescription('I-Dostavka');

            $excel->sheet('Отчет по заказам', function ($sheet) use ($orders) {
                $i = 2;
                $sheet->appendRow(['Дата', "Магазин", "Адрес", "Товар", "Сумма", "Контакт", "Время", "Доставка", "Остаток"]);

                $sheet->cell('A1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('B1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('C1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('D1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('E1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('F1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('G1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('H1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });
                $sheet->cell('I1', function ($cell) {
                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                });

                $sheet->cells('A1:I1', function ($cells) {
                    $cells->setBackground('#23395b');
                });

                $styleArray = array(
                    'font' => array(
                        'bold' => true,
                        'color' => array('rgb' => 'FFFFFF'),
                        'size' => 14
                    ));

                $sheet->getStyle('A1:I1')->applyFromArray($styleArray);

                foreach ($orders as $order) {
                    if ($order->type === 1) {

                        if ($order->status == 5) {
                            $delivery = 0;
                            $to_shop = 0;
                        } elseif ($order->status == 6) {
                            $delivery = 0;
                            $to_shop = 0;
                        } elseif ($order->status == 7) {
                            $delivery = round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                            $to_shop = $order->order_sum - round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) - $order->additional_cost;
                        } else {
                            $delivery = round($order->order_sum - $order->to_shop, 2);
                            $to_shop = $order->to_shop;
                        }

                        if ($order->goods()->count() > 1) {
                            for ($j = 0; $j < $order->goods()->count(); $j++) {
                                if ($j == 0) {
                                    $sheet->appendRow([$order->date,
                                        $order->partner->name,
                                        $order->address,
                                        $order->goods()->get()[$j]->selected ? $order->goods()->get()[$j]->name . " (выбран)" : $order->goods()->get()[$j]->name,
                                        $order->goods()->get()[$j]->price,
                                        $order->contact,
                                        $order->time,
                                        $delivery,
                                        $to_shop]);
                                } else {
                                    $sheet->appendRow([
                                        '',
                                        '',
                                        '',
                                        $order->goods()->get()[$j]->selected ? $order->goods()->get()[$j]->name . " (выбран)" : $order->goods()->get()[$j]->name,
                                        $order->goods()->get()[$j]->price,
                                        '',
                                        '',
                                        '',
                                        ''
                                    ]);
                                }
                            }

                            $sheet->setMergeColumn([
                                'columns' => ['A', 'B', 'C', 'F', 'G', 'H', 'I'],
                                'rows' => [[$i, $i + ($order->goods()->count() - 1)]],
                            ]);

                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('D' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            for ($a = $i; $a < $i + $order->goods()->count(); $a++) {
                                $sheet->cell('E' . $a, function ($cell) {
                                    $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                                });
                            }
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            for ($a = $i; $a < $i + $order->goods()->count(); $a++) {
                                $sheet->cells("A".$a.":I".$a, function($cells) {
                                    $cells->setBorder('thin', 'thin', 'thin', 'thin');
                                });
                            }

                            $i += $order->goods()->count();
                        } elseif ($order->goods()->count() == 1) {
                            $sheet->appendRow([$order->date,
                                $order->partner->name,
                                $order->address,
                                $order->goods()->first()->name,
                                $order->order_sum,
                                $order->contact,
                                $order->time,
                                $delivery,
                                $to_shop]);

                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('A' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('B' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('C' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('D' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('E' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('F' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('G' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('H' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                            });
                            $sheet->cell('I' . $i, function ($cell) {
                                $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                            });
                            $sheet->cells("A".$i.":I".$i, function($cells) {
                                $cells->setBorder('thin', 'thin', 'thin', 'thin');
                            });

                            $i++;
                        }
                    } elseif ($order->type == 2) {
                        $sheet->appendRow([
                            $order->date,
                            $order->partner->name,
                            $order->address,
                            $order->goods()->first()->name,
                            'zabor',
                            $order->contact,
                            $order->time,
                            $order->delivery_cost,
                            $order->to_shop
                        ]);

                        $sheet->cell('A' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('A' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('B' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('B' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('C' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('C' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('D' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('E' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('F' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('F' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('G' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('G' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        });
                        $sheet->cell('H' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('H' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cell('I' . $i, function ($cell) {
                            $cell->setValignment(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        });
                        $sheet->cell('I' . $i, function ($cell) {
                            $cell->setAlignment(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        });
                        $sheet->cells("A".$i.":I".$i, function($cells) {
                            $cells->setBorder('thin', 'thin', 'thin', 'thin');
                        });

                        $i++;
                    }
                }
            });

        })->download('xls');
    }

    /*Добавление курьера доставки*/
    public
    function addDeliveryCourier(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        $route = Route::where('date', $order->date)->first();

        if (!$route) {
            $route = new Route();
            $route->date = $order->date;
            $route->save();
        }

        if ($order->closed) {
            return response()->json(['success' => 0]);
        }
        $old_courier = Courier::where('type', 1)->whereHas('orders', function ($query) use ($order) {
            $query->where('orders.id', $order->id);
        })->first();
        if ($old_courier) {
            $order->couriers()->detach($old_courier->id);
        }
        $order->couriers()->attach($request->courier_id);
        $route->couriers()->attach($request->courier_id);
        $courier = Courier::where('id', $request->courier_id)->first();

        if ($old_courier) {
            History::add($order->id, 'Доставка (' . ($old_courier->transport ? 'М | ' : 'П | ') . $old_courier->name . ' ' . $old_courier->phone . ' ' . $order->date . ' - ' . ($courier->transport ? 'М | ' : 'П | ') . $courier->name . ' ' . $courier->phone . ' ' . $order->date . ')', 'cat-change');
        } else {
            History::add($order->id, 'Доставка (' . ($courier->transport ? 'М | ' : 'П | ') . $courier->name . ' ' . $courier->phone . ' ' . $order->date . ')', 'cat-change');
        }

        return response()->json([
            'success' => 1,
            'selected_courier' => (String)view('local.orders.ajax.courier', ['courier' => $courier, 'order' => $order]),
            'search_courier' => (String)view('local.orders.ajax.courier_in_search', ['courier' => $old_courier])
        ]);
    }

    /*Добавление курьера забора*/
    public
    function addPickUpCourier(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        $pick_up = PickUp::where('date', $order->date)->first();

        if (!$pick_up) {
            $pick_up = new PickUp();
            $pick_up->date = $order->date;
            $pick_up->save();
        }

        if ($order->closed) {
            return response()->json(['success' => 0]);
        }
        $old_courier = Courier::where('type', 2)->whereHas('orders', function ($query) use ($order) {
            $query->where('orders.id', $order->id);
        })->first();
        if ($old_courier) {
            $order->couriers()->detach($old_courier->id);
        }
        $order->couriers()->attach($request->courier_id);
        $pick_up->couriers()->attach($request->courier_id);
        $courier = Courier::where('id', $request->courier_id)->first();

        if ($old_courier) {
            History::add($order->id, 'Забор (' . ($old_courier->transport ? 'М | ' : 'П | ') . $old_courier->name . ' ' . $old_courier->phone . ' ' . $order->date . ' - ' . ($courier->transport ? 'М | ' : 'П | ') . $courier->name . ' ' . $courier->phone . ' ' . $order->date . ')', 'cat-change');
        } else {
            History::add($order->id, 'Забор (' . ($courier->transport ? 'М | ' : 'П | ') . $courier->name . ' ' . $courier->phone . ' ' . $order->date . ')', 'cat-change');
        }

        return response()->json([
            'success' => 1,
            'selected_courier' => (String)view('local.orders.ajax.courier', ['courier' => $courier, 'order' => $order]),
            'search_courier' => (String)view('local.orders.ajax.courier_in_search', ['courier' => $old_courier])
        ]);
    }

    /*Проверка товара на склад */
    public
    function checkStock(Request $request)
    {
        $validator = $this->checkStockValidation($request->all());

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        return response()->json(['success' => 1, 'goods' => (String)view('local.orders.ajax.goods_to_stock', ['goods' => $request->goods])]);
    }

    /*Проверка товара на склад */
    public
    function checkStockSimple(Request $request)
    {
        $validator = $this->checkStockValidation($request->all());

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }
        return response()->json(['success' => 1, 'goods' => (String)view('local.orders.ajax.goods_to_stock', ['goods' => $request->goods])]);
    }

    /*Валидация товар ана склад*/
    public
    function checkStockValidation($data)
    {
        return Validator::make($data, [
            'goods.*.name' => 'required|string|max:1000',
            'goods.*.price' => 'required|numeric',
            'partner_id' => 'required|exists:partners,id'
        ]);
    }

    /*Добавляет товар на склад из товаров на выбор*/
    public
    function addStock(Request $request)
    {
        $validator = $this->addStockValidation($request->all());

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        foreach ($request->goods as $good) {
            $stock_good = new Stock();
            $stock_good->partner_id = $request->partner_id;
            $stock_good->name = str_limit($good['name'], 990);
            $stock_good->date_on = Carbon::now()->toDateString();
            $stock_good->status = $good['status'];
            $stock_good->save();
        }
        return response()->json(['success' => 1]);
    }

    /*Добавляет товар на склад из товаров на выбор*/
    public
    function addStockSimple(Request $request)
    {
        $validator = $this->addStockValidation($request->all());

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        $stock_good = new Stock();
        $stock_good->partner_id = $request->partner_id;
        $stock_good->name = str_limit($request->good['name'], 990);
        $stock_good->date_on = Carbon::now()->toDateString();
        $stock_good->status = $request->good['status'];
        $stock_good->save();

        return response()->json(['success' => 1]);
    }

    /*Добавление товара на склад после отмены заказа*/
    public
    function addStockAfterCancel(Request $request)
    {

        $validator = $this->changeStatusValidation($request->all());

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'errors' => $validator->messages()->getMessages()]);
        }

        $order = Order::find($request->order_id);
        History::add($order->id, 'Статус (' . Config::get('order.statuses')[$order->status] . ' - ' . Config::get('order.statuses')[$request->status] . ')', 'cat-status');
        $order->status = $request->status;
        $order->save();

        $goods = OrderGood::where('order_id', $request->order_id)->get();

        foreach ($goods as $good) {
            $stock_good = new Stock();
            $stock_good->partner_id = $order->partner_id;
            $stock_good->name = str_limit($good->name, 990);
            $stock_good->date_on = Carbon::now()->toDateString();
            $stock_good->status = 1;
            $stock_good->save();
        }

        if ($request->list === false) {
            return response()->json(['success' => 1]);
        } else {
            $order_sum = 0;
            $delivery = 0;
            $to_shop = 0;

            parse_str($request->form, $form);

            if ($order->status == 6) {
                $order_sum = 0;
                $delivery = 0;
                $to_shop = 0;
            } elseif ($order->status == 7) {
                $order_sum = $order->order_sum;
                $delivery = round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
                $to_shop = $order->order_sum - round(Config('order.self_del_sum') + (($order->order_sum / 100) * Config('order.self_del_percent')), 2) + $order->additional_cost;
            } elseif ($order->status == 5) {
                $order_sum = 0;
                $delivery = 0;
                $to_shop = 0;
            } else {
                $order_sum = $order->order_sum;
                $delivery = round($order->order_sum - $order->to_shop, 2);
                $to_shop = $order->to_shop;
            }

            $all_orders = Order::when(isset($form['date']) && $form['date'] != "" && $request->page_type == "current", function ($query) use ($form) {
                return $query->where('date', "=", Carbon::createFromFormat('d/m/Y', $form['date'])->toDateString());
            })->when(isset($form['date']) && $form['date'] == "" && $request->page_type == "current", function ($query) use ($form) {
                return $query->where('date', "=", Carbon::now()->toDateString());
            })->when(isset($form['from_date']) && $form['from_date'] != "" && $request->page_type == "index", function ($query) use ($form) {
                return $query->where('date', ">=", Carbon::createFromFormat('d/m/Y', $form['from_date'])->toDateString());
            })->when(isset($form['to_date']) && $form['to_date'] && $request->page_type == "index", function ($query) use ($form) {
                return $query->where('date', "<=", Carbon::createFromFormat('d/m/Y', $form['to_date'])->toDateString());
            })->when(isset($form['order_type']) && $form['order_type'] != "", function ($query) use ($form) {
                return $query->where('type', $form['order_type']);
            })->when(isset($form['stock']) && ($form['stock'] || $form['stock'] == 0) && $form['stock'] != "", function ($query) use ($form) {
                return $query->where('in_stock', $form['stock']);
            })->when(isset($form['correct']) && ($form['correct'] || $form['correct'] == 0) && $form['correct'] != "", function ($query) use ($form) {
                return $query->where('correct_data', $form['correct']);
            })->when(isset($form['closed']) && ($form['closed'] || $form['closed'] == 0) && $form['closed'] != "", function ($query) use ($form) {
                return $query->where('closed', $form['closed']);
            })->when(isset($form['status']) && $form['status'] != "", function ($query) use ($form) {
                return $query->where('status', $form['status']);
            })->when(isset($form['partner']) && $form['partner'] != "", function ($query) use ($form) {
                return $query->where('partner_id', $form['partner']);
            })->when(isset($form['courier']) && $form['courier'] != "", function ($query) use ($form) {
                return $query->whereHas('couriers', function ($query) use ($form) {
                    $query->where('couriers.id', $form['courier']);
                });
            })->when(isset($form['pick_up']) && $form['pick_up'] != "", function ($query) use ($form) {
                return $query->whereHas('couriers', function ($query) use ($form) {
                    $query->where('couriers.id', $form['pick_up'])->where('couriers.type', 2);
                });
            })->when(isset($form['search']) && $form['search'] && $form['search'] != '', function ($query) use ($form) {
                return $query->where(function ($query) use ($form) {
                    $query->whereHas('goods', function ($query) use ($form) {
                        $query->where('name', 'ilike', '%' . trim($form['search']) . '%');
                    })->orWhere('address', 'ilike', '%' . trim($form['search']) . '%')
                        ->orWhere('contact', 'ilike', '%' . trim($form['search']) . '%');

                    if (intval(trim($form['search']))) {
                        $query->orWhere('orders.id', '=', intval(trim($form['search'])));
                    }
                    return $query;
                });
            })->select('orders.*')->get();

            $subtotal = $this->calculateSubtotal($all_orders);

            return response()->json(['success' => 1, 'order_sum' => $order_sum, 'delivery' => $delivery,
                'to_shop' => $to_shop, 'subtotal' => $subtotal]);
        }
    }

    /*Валидация товара на склад*/
    public
    function addStockValidation($data)
    {
        return Validator::make($data, [
            'goods.*.name' => 'required|string|max:255',
            'goods.*.price' => 'required|numeric',
            'goods.*.status' => 'required|in:1,2',
            'partner_id' => 'required|exists:partners,id'
        ]);
    }

    /*Массовое изминение статусов заказов*/
    public
    function changeStatusMass(Request $request)
    {

        if (isset($request->ids) && count($request->ids) > 0) {
            if ($request->name == "in_stock") {
                foreach ($request->ids as $id) {
                    $order = Order::find($id);
                    $order->in_stock = $request->value;
                    $order->save();
                    if ($request->value === true) {
                        History::add($order->id, 'На складе', 'cat-at-storage');
                    } else {
                        History::add($order->id, 'Не на складе', 'cat-at-storage');
                    }
                }
            } elseif ($request->name == "closed") {
                if (Role::inRole('tech') || Role::inRole('super')) {
                    foreach ($request->ids as $id) {
                        $order = Order::find($id);
                        $order->closed = $request->value;
                        $order->save();
                        if ($request->value === true) {
                            History::add($order->id, 'Закрыт', 'cat-closed');
                        } else {
                            History::add($order->id, 'Не закрыт', 'cat-closed');
                        }
                    }
                } else {
                    return json_encode(['success' => 'finish', 'error' => 0]);
                }
            } elseif ($request->name == "correct_data") {
                foreach ($request->ids as $id) {
                    $order = Order::find($id);
                    $order->correct_data = $request->value;
                    $order->save();
                    if ($request->value === true) {
                        History::add($order->id, 'Данные верны', 'cat-correct');
                    } else {
                        History::add($order->id, 'Данные не верны', 'cat-correct');
                    }
                }
            } elseif ($request->name == "status") {
                foreach ($request->ids as $id) {
                    $order = Order::find($id);
                    History::add($id, 'Статус (' . Config::get('order.statuses')[$order->status] . ' - ' . Config::get('order.statuses')[$request->value] . ')', 'cat-status');
                    $order->status = $request->value;
                    $order->save();
                }
            } elseif ($request->name == "change_date") {

                foreach ($request->ids as $id) {

                    $order = Order::find($id);

                    if ($order->date != Carbon::createFromFormat('d/m/Y', $request->value)->toDateString()) {

                        $courier_delivery = Courier::where('type', 1)
                            ->whereHas('orders', function ($query) use ($id) {
                                $query->where('orders.id', $id);
                            })
                            ->first();

                        if ($courier_delivery) {

                            $exists_delivery = Courier::where('id', $courier_delivery->id)
                                ->whereHas('routes', function ($query) use ($request) {
                                    $query->where('date', Carbon::createFromFormat('d/m/Y', $request->value)->toDateString());
                                })
                                ->first();

                            if (!$exists_delivery) {
                                $courier_delivery->orders()->detach($order->id);
                                History::add($order->id, 'Доставка (' . ($courier_delivery->transport ? 'М | ' : 'П | ') . $courier_delivery->name . ' ' . $courier_delivery->phone . ' ' . $order->date . ' - \' \')', 'cat-change');
                            }
                        }

                        $courier_pick_up = Courier::where('type', 2)->whereHas('orders', function ($query) use ($id) {
                            $query->where('orders.id', $id);
                        })->first();
                        if ($courier_pick_up) {
                            $exists_pick_up = Courier::where('id', $courier_pick_up->id)->whereHas('pick_ups', function ($query) use ($request) {
                                $query->where('date', Carbon::createFromFormat('d/m/Y', $request->value)->toDateString());
                            })->first();
                            if (!$exists_pick_up) {
                                $courier_pick_up->orders()->detach($order->id);
                                History::add($order->id, 'Забор (' . ($courier_pick_up->transport ? 'М | ' : 'П | ') . $courier_pick_up->name . ' ' . $courier_pick_up->phone . ' ' . $order->date . ' - \' \')', 'cat-change');
                            }
                        }

                        $order->date = Carbon::createFromFormat('d/m/Y', $request->value)->toDateString();
                        $order->save();

                    }
                }
            }

            return json_encode(['success' => 'finish', 'error' => 0]);
        } else {
            return json_encode(['success' => 0, 'error' => 'no_orders']);
        }


    }

    public
    function removeOrdersByPartner()
    {

        /*$orders = Order::where('partner_id', 18)->get();
        dd(count($orders));
        foreach ($orders as $order){
            $order->goods()->delete();
            $order->notes()->delete();
            $order->couriers()->detach();
            History::add($order->id, 'Удаление заказа', 'cat-change');
            $order->delete();
        }*/

    }
}
