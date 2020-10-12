<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Zoom;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use Firebase\JWT\JWT;

/**
 * Class ZoomService
 *
 * @package AmeliaBooking\Infrastructure\Services\Zoom
 */
class ZoomService
{
    /**
     * @var SettingsService $settingsService
     */
    private $settingsService;

    /**
     * ZoomService constructor.
     *
     * @param SettingsService $settingsService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @param string     $requestUrl
     * @param array|null $data
     * @param string     $method
     *
     * @return mixed
     */
    public function execute($requestUrl, $data, $method)
    {
        $zoomSettings = $this->settingsService->getCategorySettings('zoom');

        $token = [
            'iss' => $zoomSettings['apiKey'],
            'exp' => time() + 3600
        ];

        $ch = curl_init($requestUrl);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Authorization: Bearer ' . JWT::encode($token, $zoomSettings['apiSecret']),
                'Content-Type: application/json'
            ]
        );

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_FORCE_OBJECT));
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if ($result === false) {
            return ['message' => curl_error($ch), 'users' => null];
        }

        curl_close($ch);

        return json_decode($result, true);
    }

    /**
     *
     * @return mixed
     */
    public function getUsers()
    {
        return $this->execute('https://api.zoom.us/v2/users?page_size=300', null, 'GET');
    }

    /**
     * @param int   $userId
     * @param array $data
     *
     * @return mixed
     */
    public function createMeeting($userId, $data)
    {
        return $this->execute("https://api.zoom.us/v2/users/{$userId}/meetings", $data, 'POST');
    }

    /**
     * @param int   $meetingId
     * @param array $data
     *
     * @return mixed
     */
    public function updateMeeting($meetingId, $data)
    {
        return $this->execute("https://api.zoom.us/v2/meetings/{$meetingId}", $data, 'PATCH');
    }

    /**
     * @param int   $meetingId
     *
     * @return mixed
     */
    public function deleteMeeting($meetingId)
    {
        return $this->execute("https://api.zoom.us/v2/meetings/{$meetingId}", null, 'DELETE');
    }

    /**
     * @param int $meetingId
     *
     * @return mixed
     */
    public function getMeeting($meetingId)
    {
        return $this->execute("https://api.zoom.us/v2/meetings/{$meetingId}", null, 'GET');
    }
}
