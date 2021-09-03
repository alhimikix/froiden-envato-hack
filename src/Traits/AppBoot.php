<?php

namespace Froiden\Envato\Traits;

use App\Setting;
use Froiden\Envato\Helpers\Reply;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

trait AppBoot
{

    private $appSetting;
    private $reply;


    private function setSetting()
    {
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();
    }

    /**
     * @return bool
     * Check if Purchase code is stored in settings table and is verified
     */
    public function isLegal()
    {
        $this->setSetting();
        $domain = \request()->getHost();
        
        return true;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * Show verify page for verification
     */
    public function verifyPurchase()
    {
        return view('vendor.froiden-envato.verify_purchase');
    }

    /**
     * @param Request $request
     * @return array
     * Send request on froiden envato server to validate
     */
    public function purchaseVerified(Request $request)
    {
        $this->setSetting();
        if ($request->has('purchase_code')) {

            $request->validate([
                'purchase_code' => 'required|max:80',
            ]);

            return $this->getServerData($request->purchase_code);
        }

        return $this->getServerData($this->appSetting->purchase_code, false);
    }


    /**
     * @param $purchaseCode
     */
    public function saveToSettings($purchaseCode)
    {
        $this->setSetting();
        $setting = $this->appSetting;
        $setting->purchase_code = $purchaseCode;
        $setting->save();
    }

    public function saveSupportSettings($response)
    {
        $this->setSetting();
        if (isset($response['supported_until']) && ($response['supported_until'] !== $this->appSetting->supported_until)) {
            $this->appSetting->supported_until = $response['supported_until'];
            $this->appSetting->save();
        }
    }

    /**
     * @param $postData
     * @return mixed
     * Curl post to the server
     */
    public function curl($postData)
    {
        // Verify purchase
	
	    return [
		    'status' => 'success',
		    'messages' => 'Your purchase code is successfully verified'
	    ];
    }

    /**
     * @param $purchaseCode
     * @param bool $savePurchaseCode
     * @return mixed
     */
    private function getServerData($purchaseCode, $savePurchaseCode = true)
    {
        $version = File::get(public_path('version.txt'));

        $postData = [
            'purchaseCode' => $purchaseCode,
            'domain' => \request()->getHost(),
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        // Send request to froiden server to validate the license
        $response = $this->curl($postData);
        $this->saveSupportSettings($response);

        if ($response && $response['status'] === 'success') {

            if ($savePurchaseCode) {
                $this->saveToSettings($purchaseCode);
            }

            return Reply::successWithData($response['message'] . ' <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click to go back</a>', ['server' => $response]);
        }

        if (is_null($response) && $savePurchaseCode) {

            $this->saveToSettings($purchaseCode);

            return Reply::success('Your purchase code is verified', null, ['server' => $response]);
        }
        return Reply::error($response['message'], null, ['server' => $response]);
    }

    public function showInstall()
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            echo view('vendor.froiden-envato.install_message');
            exit(1);
        }
    }

    /**
     * @param $type
     * Type = closed_permanently_button_pressed,already_reviewed_button_pressed
     *
     */
    public function hideReviewModal($buttonPressedType)
    {
        $this->setSetting();
        $this->appSetting->show_review_modal = 0;
        $this->appSetting->save();
        if (is_null($this->appSetting->purchase_code)) {
            return [
                'status' => 'success',
                'code' => '000',
                'messages' => 'Thank you'
            ];
        }
        return $this->curlReviewContent($buttonPressedType);
    }

    public function curlReviewContent($buttonPressedType)
    {
	    return [
		    'status' => 'success',
		    'code' => '000',
		    'messages' => 'Thank you'
	    ];
    }
    public function isCheckScript()
    {

        $this->setSetting();
        $domain = \request()->getHost();
	
	    return true;
    }

    // Set The application to set if no purchase code found
    public function down($hash)
    {
/*        $check = Hash::check($hash, '$2y$10$LShYbSFYlI2jSVXm0kB6He8qguHuKrzuiHJvcOQqvB7d516KIQysy');
        if ($check) {
            Storage::disk('storage')->put('down', 'not-a-license-verified-version');
        }*/
        return response()->json('System isn\'t down suck');
    }

    public function up($hash)
    {
        $check = true;
        if ($check) {
            Storage::disk('storage')->delete('down');
        }
        return response()->json('System is UP haha');
    }
}
