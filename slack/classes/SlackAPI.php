<?php

/**
* @description : Functions for use the Slack API
*/

if (!defined('_PS_VERSION_'))
    exit;

class SlackAPI
{
    public static function checkTokenValidity($token)
    {
        $parameters = array('token' => $token);
        return self::curlCall('auth.test', $parameters);
    }

    public static function getChannelsListByToken($token, $with_private_channel = true)
    {
        $method = 'channels.list';
        $parameters = array('token' => $token);
        $result = self::curlCall($method, $parameters);

        if ($result->ok) {
            $channels = array();
            foreach ($result->channels as $channel) {
                    $channels[$channel->id] = $channel->name;
            }

            if ($with_private_channel) {
                $result = self::curlCall('groups.list', $parameters);

                foreach ($result->groups as $channel_private) {
                    $channels[$channel_private->id] = $channel_private->name." (private)";
                }
            }
        }
        return $channels;
    }

    public static function curlCall($method, $parameters)
    {
        if (!$method) {
            die("method not defined");
        } else {
            $url = 'https://slack.com/api/'.$method;
        }

        if ($query = http_build_query($parameters)) {
            $url .= '?' . $query;
        }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

        return json_decode($result);
    }
}
