<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;

class LiveKitService
{
    public function generateToken(
        string $roomName,
        string $participantName
    ): string {

        \dd("api_key=> ",config('livekit.api_key'),
            "api_secret=> ",config('livekit.api_secret'),
            "url=> ",config('livekit.url'));

        // 1. Define participant options (Identity and Name)
        $options = (new AccessTokenOptions())
            ->setIdentity($participantName)
            ->setName($participantName);

        // 2. Initialize the Token with API credentials and options
        $token = new AccessToken(
            config('livekit.api_key'),
            config('livekit.api_secret'),
            $options
        );

        // 3. Define the Room permissions (Grants)
        $videoGrant = (new VideoGrant())
            ->setRoomJoin()      // Permission to join
            ->setRoomName($roomName); // Specify the room name

        // 4. Attach the grant to the token
        $token->setGrant($videoGrant);
        
        return $token->toJwt();
    }
}
