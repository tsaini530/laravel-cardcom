<?php

namespace Dbws\Cardcom;

use GuzzleHttp;
use InvalidArgumentException;

class Cardcom
{
    /**
     * The base Cardcom Api URL.
     *
     * @var string
     */
    protected $url = 'https://secure.cardcom.co.il';

    /**
     * The Cardcom terminal number.
     *
     * @var string
     */
    protected $terminal;

    /**
     * The Cardcom terminal username.
     *
     * @var string
     */
    protected $username;

    /**
     *  The Cardcom api name.
     *
     * @var string
     */
    protected $apiName;

    /**
     *  The Cardcom api password.
     *
     * @var string
     */
    protected $apiPassword;

    /**
     *  Credit card.
     *
     * @var array
     */
    protected $card;

    /**
     *  Invoice.
     *
     * @var array
     */
    protected $invoice;

    /**
     * Cardcom constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->setConfig($config);
    }

    /**
     * Set the Cardcom terminal.
     *
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->terminal = $config['terminal'];
        $this->username = $config['username'];
        $this->apiName = $config['api_name'] ?? '';
        $this->apiPassword = $config['api_password'] ?? '';

        return $this;
    }



    
    /**
     * Set the invoice.
     *
     * @param array $params
     *
     * @return $this
     */
    public function invoice(array $params)
    {
        $this->invoice = $params;

        return $this;
    }

    /**
     * Add invoice item.
     *
     * @param array $params
     *
     * @return $this
     */
    public function invoiceItem(array $params)
    {
        $this->invoice['items'][] = $params;

        return $this;
    }

    
    

    /**
     * Get invoice.
     *
     * @param int  $number
     * @param bool $pdf
     * @param int  $type
     * @param bool $origin
     *
     * @return mixed
     */
    public function getInvoice($number, $pdf = false, $type = 1, $origin = false)
    {
        $auth = [
            'userName'     => $this->apiName,
            'userPassword' => $this->apiPassword,
        ];

        if ($pdf) {
            $params = [
                'documentNumber' => $number,
                'documentType'   => $type,
                'isOriginal'     => $origin,
            ];

            $path = '/Interface/GetDocumentPDF.aspx';
        } else {
            $params = [
                'invoiceNumber' => $number,
                'invoiceType'   => $type,
                'getAsOriginal' => $origin,
            ];

            $path = '/Interface/InvoiceGetHtml.aspx';
        }

        $client = new GuzzleHttp\Client();

        $response = $client->request('POST', $this->url.$path, [
            'form_params' => array_merge($auth, $params),
        ]);

        return $response->getBody();
    }

    /**
     * Request.
     *
     * @param array  $params
     * @param string $path
     * @param string $separator
     *
     * @return mixed
     */
    protected function request($params, $path = '/Interface/Direct2.aspx', $separator = '&')
    {
        $client = new GuzzleHttp\Client();

        $response = $client->request('POST', $this->url.$path, [
            'form_params' => $params,
        ]);

        return $this->response($response->getBody(), $separator);
    }

    /**
     * Response.
     *
     * @param string $action
     * @param string $response
     *
     * @return mixed
     */
    public function response($response, $separator = '&')
    {
        if ($separator == '&') {
            parse_str($response, $array);

            $data = [
                'code'        => $array['ResponseCode'],
                'message'     => $array['Description'],
                'transaction' => $array['InternalDealNumber']??'',
            ];
        }

        if ($separator == ';') {
            $array = explode($separator, $response);

            $data = [
                'code'        => $array[0],
                'message'     => $array[2],
                'transaction' => $array[1],
            ];
        }

        if (!is_array($array)) {
            return $response;
        }

        if (isset($array['Token'])) {
            $data['token'] = $array['Token'];
        }

        if (isset($array['ApprovalNumber'])) {
            $data['approval'] = $array['ApprovalNumber'];
        }

        if (isset($array['InvoiceResponse_ResponseCode'])) {
            $data['invoice'] = [
                'code'    => $array['InvoiceResponse_ResponseCode'],
                'message' => $array['InvoiceResponse_Description'],
                'number'  => $array['InvoiceResponse_InvoiceNumber'],
                'type'    => $array['InvoiceResponse_InvoiceType'],
            ];
        }
        if (isset($array['url'])) {
            $data['url'] = $array['url'];
        }


        $data['payload'] = $array;

        return $data;
    }

    /**
     * Cardcom currency supported.
     * More information at http://kb.cardcom.co.il/article/AA-00247/0.
     *
     * @param string $code
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    public function currency($code)
    {
        $currency = strtoupper($code);

        $currencies = [
            'ILS' => 1,
            'USD' => 2,
            'AUD' => 36,
            'CAD' => 124,
            'DKK' => 208,
            'JPY' => 392,
            'NZD' => 554,
            'RUB' => 643,
            'CHF' => 756,
            'GBP' => 826,
            'EUR' => 978,
        ];

        if (!isset($currencies[$currency])) {
            throw new InvalidArgumentException("Unsupported currency [{$code}].");
        }

        return $currencies[$currency];
    }
    /**
     * Create a low profile code.
     *
     * @param int    $amount
     * @param string $currency
     * @param string $success_url     
     * @param string $error_url
     * @param string $indicator
     * @param int   $operation
     * @param int $codepage
     *
     * @return mixed
     */
    public function generateLowProfile($amount, $success_url,$error_url ,$codepage=65001,$operation=3,$indicator="page.aspx",$currency = 'ILS'){
        $params = [
            'TerminalNumber'    => $this->terminal,
            'UserName'          => $this->username,
            'SumToBill'         => $amount,
            'APILevel'          =>  10,
            'codepage'          => $codepage,
            'coinId'            => $this->currency($currency),
            'IndicatorUrl'      => $this->url.$indicator,
            'SuccessRedirectUrl'=> $success_url,
            'ErrorRedirectUrl'  => $error_url,
            'Operation'         => $operation,
            'ReturnValue'       => 3345,
            'HideCreditCardUserId'=>"true",
            'HideCardOwnerName'   => "true",
            'ShowCardOwnerPhone'  => "false",
            'ShowCardOwnerEmail'  => "false",
            'ProductName'         => 'monthly subscription',
            
        ];
        return $this->request($params,'/Interface/LowProfile.aspx');
    }
    /**
     * check and verify low profile code.
     *
     * @param string $lowprofilecode
     *
     * @return mixed
     */
    public function checkLowProfile($lowprofilecode){
        $params = [
            'terminalNumber'    => $this->terminal,
            'userName'          => $this->username,
            'LowProfileCode'    => $lowprofilecode
        ];
        return $this->request($params,'/Interface/BillGoldGetLowProfileIndicator.aspx');


    }
   /**
     * Charge given amount.
     *
     * @param int    $amount
     * @param string $currency
     * @param string $payments
     * @param array   $userData
     * @param int $codepage
     *
     * @return mixed
     */
   public function payment($amount,$userData,$currency = 'ILS', $payments = 1, $codepage=65001)
   {   
    $CardValidityYear = substr($userData['CardValidityYear'], -2);
    $items = [];
    $invoice = [];
    $charge = [
        'TerminalNumber'                    => $this->terminal,
        'UserName'                          => $this->username,
        'TokenToCharge.APILevel'            =>  9,
        'TokenToCharge.Token'               =>$userData['Token'],
        'TokenToCharge.CardValidityMonth'   => $userData['CardValidityMonth'],
        'TokenToCharge.CardValidityYear'    => $CardValidityYear,
        'TokenToCharge.SumToBill'           => $amount,
        'TokenToCharge.coinId'              => $this->currency($currency),
        'TokenToCharge.numOfPayments'       => $payments,
        'codepage'                          => $codepage,

    ];


    if (!empty($this->invoice)) {
        $invoice = [
            'InvoiceHead.CreateInvoice'  => true,
            'InvoiceHead.CustId'         => $this->invoice['identity'] ?? null,
            'Invcustnumber'              => $this->invoice['customer_id'] ?? null,
            'dealidentitycode'           => $this->invoice['identity'] ?? null,
            'InvoiceHead.CustAddresLine1'=> $this->invoice['address_1'] ?? null,
            'InvoiceHead.CustAddresLine2'=> $this->invoice['address_2'] ?? null,
            'InvoiceHead.CustCity'       => $this->invoice['city'] ?? null,
            'InvoiceHead.Email'          => $this->invoice['email'],
            'InvoiceHead.CustName'       => $this->invoice['customer_name'],
            'InvoiceHead.CustLinePH'     => $this->invoice['phone'] ?? null,
            'InvoiceHead.CustMobilePH'   => $this->invoice['mobile'] ?? null,
            'InvComments'                => $this->invoice['comments'] ?? null,
            'InvoiceHead.Language'       => $this->invoice['invoice_language'] ?? 'he',
            'languages'                  => $this->invoice['invoice_language'] ?? 'he',
            'InvoiceHead.NoVat'          => $this->invoice['vat_free'] ?? false,
            'InvoiceHead.SendByEmail'    => $this->invoice['send_email']??False,
        ];

        if (!isset($this->invoice['items']) || empty($this->invoice['items'])) {
            $invoice['invItemDescription'] = $this->invoice['description'] ?? '';
            $invoice['InvProductID'] = $this->invoice['product_id'] ?? null;
        } else {
            foreach ($this->invoice['items'] as $key => $item) {
                $line = $key == 0 ? '' : $key;

                $items["InvoiceLines{$line}.Description"] = $item['description'];
                $items["InvoiceLines{$line}.PriceIncludeVAT"] = $item['price'];
                $items["InvoiceLines{$line}.Quantity"] = $item['quantity'] ?? '1';
                $items["InvoiceLines{$line}.ProductID"] = $item['id'] ?? null;
                $items["InvoiceLines{$line}.IsVatFree"] = $item['vat_free'] ?? false;
            }
        }
    }
    return $this->request(array_merge($charge, $invoice, $items), '/Interface/ChargeToken.aspx');

}

}
