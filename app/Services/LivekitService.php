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

        // 1. Define participant options (Identity and Name)
        $options = (new AccessTokenOptions())
            ->setIdentity($participantName)
            ->setName($participantName);

        $apiKey = config('livekit.api_key');
        $apiSecret = config('livekit.api_secret');

        if (empty($apiKey) || empty($apiSecret)) {
            throw new \InvalidArgumentException("LiveKit API Key or Secret is not configured. Please check LIVEKIT_API_KEY and LIVEKIT_API_SECRET in the production environment settings.");
        }

        // 2. Initialize the Token with API credentials and options
        $token = new AccessToken(
            $apiKey,
            $apiSecret,
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
