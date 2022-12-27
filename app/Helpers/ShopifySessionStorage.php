<?php

namespace App\Helpers;

use Shopify\Auth\SessionStorage;
use Shopify\Auth\Session;
use App\Models\Shopify\Session as ShopifySession;
use Illuminate\Support\Facades\Log;

/**
 * Description of ShopifySessionStorage
 *
 * @author Pampyras
 */
class ShopifySessionStorage implements SessionStorage
{

    /**
     * Initializes ShopifySessionStorage object
     *
     * @param string $path Path to store the session files in
     */
    public function __construct(string $path = '')
    {
        //$path for backward compatibility
    }

    /**
     * Loads the Session object from the serialized file
     *
     * @param string $sessionId Id of the Session that is being loaded
     * @return Session Returns Session if found, null otherwise
     */
    public function loadSession(string $sessionId): ?Session
    {
        $shopifySession = ShopifySession::where('session_id', $sessionId)->first();
        if (!$shopifySession) {
            return null;
        }
        try {
            $data = json_decode($shopifySession->session_data, true);
            if (!empty($data)) {
                $session = new Session($data['id'], $data['shop'], $data['isOnline'], $data['state']);
                return $session;
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to load session by id ' . $sessionId . 'Error: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Stores session into a file
     *
     * @param Session $session An instance of the session class to be stored in a file
     * @return bool True if the number of bytes stored by file_put_contents() is > 0, false otherwise
     */
    public function storeSession(Session $session): bool
    {
        $shopify_session = ShopifySession::updateOrCreate(['session_id'=> $session->getId()], ['session_data' => json_encode($session)]);
        return $shopify_session->id ? true : false;
    }

    /**
     * Deletes a Session file
     *
     * @param string $sessionId The ID of the Session to be deleted
     * @return bool Returns True if the file has been deleted or didn't exist
     */
    public function deleteSession(string $sessionId): bool
    {
        ShopifySession::where('session_id', $sessionId)->delete();
        return true;
    }
}
