<?php

namespace Eusebiu\LaravelSparkGoogle2FA;

use Laravel\Spark\Spark;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Validation\ValidationException;
use Laravel\Spark\Contracts\Interactions\Settings\Security\EnableTwoFactorAuth;
use Laravel\Spark\Http\Controllers\Settings\Security\TwoFactorAuthController as Controller;

class TwoFactorAuthController extends Controller
{
    /**
     * @var \PragmaRX\Google2FA\Google2FA
     */
    protected $g2fa;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->g2fa = new Google2FA;
    }

    /**
     * Generate a QR code.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generate(Request $request)
    {
        $secret = $this->g2fa->generateSecretKey();

        $request->session()->put('spark:twofactor:secret', $secret);

        return [
            'secret' => $secret,
            'qrcode' => $this->qrCode($request->user()->email, $secret),
        ];
    }

    /**
     * Enable Google two-factor authentication.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function enable2fa(Request $request)
    {
        $this->validate($request, ['code' => 'required|min:6']);

        $secret = $request->session()->get('spark:twofactor:secret');

        if (! $this->g2fa->verifyKey($secret, $request->code)) {
            throw ValidationException::withMessages([
               'code' => [__('The code is invalid.')],
           ]);
        }

        Spark::interact(EnableTwoFactorAuth::class, [$request->user()]);

        return $this->storeTwoFactorInformation($request);
    }

    /**
     * Get the qr code image url.
     *
     * @param  string $email
     * @param  string $secret
     * @return string
     */
    protected function qrCode($email, $secret)
    {
        $company = Spark::$details['2fa_name'] ??
                    Spark::$details['vendor'] ??
                    url()->to('/');

        return 'https://chart.googleapis.com/chart?chs=260x260&chld=M|0&cht=qr&chl=' . $this->g2fa->getQRCodeUrl(urlencode($company), $email, $secret);
    }
}
