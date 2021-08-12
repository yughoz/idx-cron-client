<?php
include("config.php");


class Scalping 
{
    
    public  $config = [];
    public  $ID_TABLE       = 0; 
    public  $balance_idr    = 0; 
    public  $balance_coin   = 0;
    public  $balance_hold_coin = 0; //balance_hold
    public  $coin           = "";
    public  $difference     = 0;
    private $price_buy      = 0;
    private $price_sell     = 0;
    private $your_buy       = 0;
    private $your_sell      = 0;
    private $ready_buy      = false;
    private $ready_sell     = false;
    private $status_buy     = false; //order_id buy
    private $status_sell    = false; //order_id sell
    private $cancel_buy_all = false; //order_id buy
    private $cancel_sell_all = false; //order_id buy
    private $skip_buy       = false; //order_id buy
    private $skip_sell      = false; //order_id buy
    private $min_sell       = 1; //minimal jual di harga ini + 1%
    private $set_maxsaldo   = 2000000;
    private $order_buy_pending = 0; //count order buy pending



    private $url_private = 'https://indodax.com/tapi';
    private $url_list_data = 'http://localhost:8000/api/';

    // Please find Key from trade API Indodax exchange
    private $key = '';
    // Please find Secret Key from trade API Indodax exchange
    private $secretKey = '';
   
    //email from api 
    private $email = '';
    

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        // parent::__construct();
        // $this->key = env("INDODAX_KEY");
        // $this->secretKey = env("INDODAX_SECRETKEY");
        // $this->email = env("INDODAX_MAIL");
    }
    public function init()
    {
        $this->key          = $this->config["INDODAX_KEY"];
        $this->secretKey    = $this->config["INDODAX_SECRETKEY"];
        $this->email        = $this->config["INDODAX_MAIL"];


        $this->init_server();
    }


    public function init_server()
    {
        $url = $this->config['url_server']."api/cron/".$this->ID_TABLE;
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);

        if(!empty($json->id_coin) && $json->active){
            $this->log("true");

            $this->coin = $json->id_coin;

            $this->set_maxsaldo = $json->max_saldo;
            $this->skip_buy     = $json->skip_buy;
            $this->skip_sell    = $json->skip_sell;


            $this->report_server();


            if (!empty($this->coin)) {
                $this->get_depth_koin($this->coin);

                for ($i=0; $i < 5; $i++) { 
                    //init

                    $this->cancel_buy_all = false;
                    $this->cancel_sell_all = false;


                    $this->get_balance();
                    $this->info("balance idr    =". number_format($this->balance_idr) );
                    $this->info("balance ".$this->coin."    :". $this->balance_coin." | ".($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy);
                    
                    $this->get_depth_koin($this->coin);
        
                    
                    $this->get_buy();

                    if (!$this->skip_buy) {
                        $this->set_buy(); 
                    } else {
                        $this->warn("buy            : SKIP!");

                    }
                    if (!$this->skip_sell) {
                        $this->set_sell();
                    } else {
                        $this->warn("sell            : SKIP!");
                    }

                    
                    $this->info("buy            : ".$this->your_buy);
                    $this->info("sell           : ".$this->your_sell);
                    // $this->info("min_sell       : ".$this->min_sell);
                    $this->warn("============". $this->coin."==========\n");
                    $this->report_server();

                    sleep(10);

        
                }
                $this->send_report();

            } else {
                $this->coin = $this->ask('koin kosong!');
            }

        } elseif (isset($json->active) && $json->active == 0) {
            $this->log("cron tidak aktif");
            
        } else {

            $this->log("\n".$url);
            $this->log("else ".$response);
        }

        echo print_r($json);
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    // public function handle()
    // {
    //     $this->coin = $this->argument('koin');
    //     if (empty($this->coin)) {
    //         $this->coin = $this->ask('Masukan nama koin?');
    //     }

    //     if (!empty($this->ask('More Optiion ? '))) {
    //         $this->expertQuestion();
    //     }

    //     $this->report_server();

    //     if (!empty($this->coin)) {
    //         $this->get_depth_koin($this->coin);

    //         for ($i=0; $i < 1000; $i++) { 
    //             //init

    //             $this->cancel_buy_all = false;
    //             $this->cancel_sell_all = false;


    //             $this->get_balance();
    //             $this->info("balance idr    =". number_format($this->balance_idr) );
    //             $this->info("balance ".$this->coin."    :". $this->balance_coin." | ".($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy);

    //             $this->get_depth_koin($this->coin);
    
                
    //             $this->get_buy();

    //             if (!$this->skip_buy) {
    //                 $this->set_buy(); 
    //             } else {
    //                 $this->warn("buy            : SKIP!");

    //             }
    //             if (!$this->skip_sell) {
    //                 $this->set_sell();
    //             } else {
    //                 $this->warn("sell            : SKIP!");
    //             }

                
    //             $this->info("buy            : ".$this->your_buy);
    //             $this->info("sell           : ".$this->your_sell);
    //             // $this->info("min_sell       : ".$this->min_sell);
    //             $this->warn("============". $this->coin."==========\n");
    //             $this->report_server();

    //             sleep(10);

    
    //         }

    //     } else {
    //         $this->coin = $this->ask('koin kosong!');
    //     }


    // }

    // public function expertQuestion()
    // {
    //     $this->set_maxsaldo = ($this->ask('max saldo ?') ?? 0) * 1000000;
    //     $this->skip_buy = $this->ask('skip buy? ') ?? false;
    //     $this->skip_sell = $this->ask('skip sell?') ?? false;
    // }

    public function get_balance()
    {
        $data = [
	        'method' => 'getInfo',
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        if (!empty($res_json['success'])) {
            $this->balance_idr = $res_json['return']['balance']['idr'];
            $this->balance_coin= $res_json['return']['balance'][$this->coin];
            $this->balance_hold_coin= $res_json['return']['balance_hold'][$this->coin];
        } else{
            $this->error("ERR get_balance   = ". $response);
            exit();
        }
        // sleep(1);
    }


    public function report_server()
    {
        $url = $this->url_list_data.'engine-scalping';
    
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query([
                'key'       => $this->key,
                'email'     => $this->email,
                'coin'      => $this->coin,
                'coin_name' => $this->coin,
                'secretKey' => $this->secretKey,
            ]),
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);
        // echo $response ;die;    

        if (!empty($json->error)) {
            $this->error("ERR report_server= ". $response);
            exit();
            // echo $response ;die;    
        } else {
            
        }
    
        
    }
    public function get_depth_koin($pair_id)
    {
        $url = 'https://indodax.com/api/depth/'.$pair_id."idr";
    
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);

        if (!empty($json->error)) {
            $this->error("ERR   get_depth_koin= ". $response);
            exit();
            // echo $response ;die;    
        } else {
            $ticker_sell = 0;
            $ticker_buy = 0;
            foreach ($json->buy as $key => $value) {
                $hitung = $value[0] * $value[1];
                if ($hitung > 90000) {
                    $ticker_buy = $value[0];
                    break;    
                }
            }

            foreach ($json->sell as $key => $value) {
                $hitung = $value[0] * $value[1];
                if ($hitung > 90000) {
                    $ticker_sell = $value[0];
                    break;    
                }
            }


            // $this->difference = (($json->ticker->sell-1) - ($json->ticker->buy+1))/($json->ticker->buy+1) * 100;


            $this->difference = (($ticker_sell-1) - ($ticker_buy+1))/($ticker_buy+1) * 100;
            if ($this->difference < 1 ) {
                $this->ready_buy = false;
                $this->error("difference     : ".$this->difference);
            } else {
                $this->ready_buy = true;
                $this->info("difference      : ".$this->difference);
            }

            

            if ($this->difference < 0.5 ) {
                $this->ready_sell = false;
            } else {
                $this->ready_sell = true;

            }

            $this->price_sell       = ($ticker_sell);
            $this->price_buy        = ($ticker_buy);
        }
    }
    
    public function get_buy()
    {
        $data = [
	        'method' => 'openOrders',
	        'pair'  => $this->coin."_idr",
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        $this->status_sell = false;
        $this->status_buy = false;
        $this->order_buy_pending = 0;
        
        if (!empty ($res_json['success'])) {
            // $this->info(print_r($res_json['return']['orders'])); 
            $this->get_depth_koin($this->coin);

            foreach ($res_json['return']['orders'] as $key => $value) {

                if ($value['type'] == "buy") {

                    if ($value['price'] < $this->price_buy || $this->difference < 1 || $this->cancel_buy_all || $this->skip_buy) { //jika harga tidak paling atas di cancel
                        $this->warn("cancel order buy   :".$value['price'] ."-----". $this->price_buy);  
                        //cancel order
                        $this->cancel_order($value['order_id'],'buy');
                        
                        $this->status_buy = false;
                        $this->cancel_buy_all = true;
                        $this->order_buy_pending = 0;
                    } else {
                        $this->status_buy = true;
                        $this->order_buy_pending += $value['order_idr'];
                    }
                    
                } else {
                    $this->status_sell = true;

                    // if ($this->min_sell < $value['price'] ) {
                    if ($this->difference < 0.5 ) {
                        $this->error("cancel order sell   : HOLD ".$value['price'] ."-----". $this->price_sell);  
                        
                    } else {
                        if ($value['price'] > $this->price_sell || $this->cancel_sell_all) { //jika harga tidak paling atas di cancel
                            $this->warn("cancel order sell   :".$value['price'] ."-----". $this->price_sell);  
                            //cancel order
                            $this->cancel_order($value['order_id'],'sell');
                            
                            $this->status_sell = false;
                            $this->cancel_sell_all = true;

                        }
                    }
                }
                    
            }
            // $this->balance_idr = $res_json['return']['balance']['idr'];
            // $this->balance_coin= $res_json['return']['balance'][$this->coin];
        } else {
            $this->error("ERR get_buy   : ".$response);  
        }
    }


    public function cancel_order($order_id,$type)
    {
        $data = [
	        'method' => 'cancelOrder',
            'pair' => $this->coin.'_idr',
            'order_id' => $order_id,
            'type'      => $type,
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        if (!empty( $res_json['success'])) {
            $this->balance_idr = $res_json['return']['balance']['idr'];
            $this->balance_coin= $res_json['return']['balance'][$this->coin];
        } else{
            $this->error("ERR cancel_order  = ". $response);
            exit();
        }
    }

    
    public function set_buy()
    {
        $idr_buy = $this->balance_idr;
        $check_buy = $idr_buy;
        if ($this->set_maxsaldo) {
            $idr_buy    = $this->set_maxsaldo - (($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy) - $this->order_buy_pending;
            $check_buy  = $this->balance_idr - $idr_buy;

            $this->info("set_maxsaldo      : ".($this->set_maxsaldo));
            $this->info("order_buy_pending      : ".($this->order_buy_pending));


            if ($check_buy < 0 ) {
                $idr_buy = $this->balance_idr;
            }
        }
        if ($this->ready_buy && $idr_buy > 10000) {
            
            $this->your_buy         = ($this->price_buy + 1);


            $data = [
                'method' => 'trade',
                'timestamp' => '1578304294000',
                'recvWindow' => '1578303937000',
                'pair' => $this->coin.'_idr',
                'type' => 'buy',
                'price' => $this->your_buy,
                'idr' => $idr_buy,
                // $this->coin => '109.85568181'
            ];
            $post_data = http_build_query($data, '', '&');
            $sign = hash_hmac('sha512', $post_data, $this->secretKey);
            
            $headers = ['Key:'.$this->key,'Sign:'.$sign];
    
            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_URL => $this->url_private,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true
            ));
    
            $response = curl_exec($curl);
            
            curl_close($curl);
            $res_json = json_decode($response,true);
            if (!empty( $res_json['success'])) {
                $this->warn("set buy price      : ".$this->your_buy);
                $this->warn("set buy nominal    : ".$res_json['return']['remain_rp']);
                $this->cancel_buy_all = false;
            } else {
                $this->error("ERR  set_buy   : ".$response);

            }
            
        } else {
            // $this->info("set buy        : ready_buy".$this->ready_buy);
            // $this->info("set buy        : status_buy".$this->status_buy);
            // $this->info("set buy        : balance_idr".$this->balance_idr);
        }

    }

    public function set_sell()
    {

        // if ($this->ready_sell && !$this->status_sell && $this->balance_coin > 0) {
        if ($this->ready_sell && $this->balance_coin > 0) {
            $this->your_sell        = ($this->price_sell- 1);

            $data = [
                'method' => 'trade',
                'timestamp' => '1578304294000',
                'recvWindow' => '1578303937000',
                'pair' => $this->coin.'_idr',
                'type' => 'sell',
                'price' => $this->your_sell,
                // 'idr' => $this->balance_idr,
                $this->coin => $this->balance_coin
            ];
            $post_data = http_build_query($data, '', '&');
            $sign = hash_hmac('sha512', $post_data, $this->secretKey);
            
            $headers = ['Key:'.$this->key,'Sign:'.$sign];
            
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_URL => $this->url_private,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true
            ));
            
            $response = curl_exec($curl);
            
            curl_close($curl);
            $res_json = json_decode($response,true);
                // $this->error("ERR       : ".$response);
            if (!empty( $res_json['success'])) {
                $this->warn("set sell price      : ".$this->your_sell);
                // $this->warn("set sell nominal    : ".$res_json['return']['remain_rp']);
                
            } else {
                $this->error("ERR set_sell : ".$response);
            
            }
            
        } elseif (!$this->ready_sell && $this->balance_coin > 0) { 
            $this->warn("set sell        : HOLD! ");
        
        }else {
            // $this->info("set sell        : else");
        }
    }
    

    function send_report()
    {
                    
        $curl = curl_init();
        $data = http_build_query([
                    "JSON_RAW"      => [
                        "balance_idr" => number_format($this->balance_idr) ,
                        $this->coin." price/diff"   => number_format($this->price_buy)."/".$this->difference,
                        "balance_coin" => intval($this->balance_coin) ."|".number_format(($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy) ,
                    ],
                    "ID_TABLE"      => $this->ID_TABLE,
                ]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url_server']."api/send_report",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
            "authorization: YVA0eGVwZTh5RzRuTUgyVEdCMFlETXhxWXMweWh4Mm1NU2tCYUhsOFl4TmtqZDZjdkFsNEJldXZOWGFuZDczdg",
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            // $this->Loging("cron_auto_replay_error" , $err);
            echo "cURL Error #:" . $err;
        } else {
            // $this->Loging("cron_auto_replay_success" , ['response' => $response]);
            echo $response;
        }
    }
    
    public function warn($text)
    {
        echo  "\e[33m".$text."\e[0m\n";
    }

    public function info($text)
    {
        echo  "\e[32m".$text."\e[0m\n";
    }

    public function error($text)
    {
        echo  "\e[31m".$text."\e[0m\n";

    }

    public function log($text)
    {
        echo "\e[21m".$text;
    }


}


if (isset($argc)) {
    $val = getopt(null, ["id:"]);


    if (empty($val['id'])) {
        echo "\nrun :\nphp scalping.php --id=1\n";die;
    }

    $scalping = new Scalping();
    $scalping->config = $config;
    $scalping->ID_TABLE = $val['id'];
    $scalping->init();

}
die;

// Scalping::init();