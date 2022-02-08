<?php
    
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('max_execution_time', 300); // 300 seconds = 5 minutes
    
    error_reporting(E_ALL);
    
    define('OAUTH2_CLIENT_ID', '000000000000000000'); // L'ID de l'application Discord // Discord application ID
    define('OAUTH2_CLIENT_SECRET', 'abcdefghijklmno-pqrstuvwxyzabcde'); // Le Secret de l'application Discord // Discord app Secret
    
    // Les URLs de l'API Discord // Discord API URLs
    $authorizeURL = 'https://discord.com/api/v9/oauth2/authorize';
    $tokenURL = 'https://discord.com/api/v9/oauth2/token';
    $apiURLBase = 'https://discord.com/api/v9/users/@me';
    $apiURLBaseGuilds = 'https://discord.com/api/v9/users/@me/guilds';
    
    session_start();
    
    // Redireigier vers la page de connexion de Discord // Redirect to Discord login page
    if(get('action') == 'login') {
    
        $params = array(
            'client_id' => OAUTH2_CLIENT_ID,
            'redirect_uri' => 'https://www.example.com/discord/',
            'response_type' => 'code',
            'scope' => 'identify'
        );
    
        header('Location:' . $authorizeURL . '?' . http_build_query($params));
        die();
    }
    
    // ==================================================
    // ===================== IF GET =====================
    // ==================================================
    
    // Quand on a la réponse de Discord // When get Discord login response
    if(get('code')) {
    
        $token = apiRequest($tokenURL, array(
            "grant_type" => "authorization_code",
            'client_id' => OAUTH2_CLIENT_ID,
            'client_secret' => OAUTH2_CLIENT_SECRET,
            'redirect_uri' => 'https://www.example.com/discord/',
            'code' => get('code')
        ));
        $_SESSION['access_token'] = $token->access_token;
        
        setcookie("refresh_token", $token->refresh_token, time()+60*60*24*30, "", "", true, true);
        
        header('Location: https://www.example.com/discord/'); // recharger la page sans "?code=VZUhfeghjgfYUZGUIgGIZ..." // Reload page without "?code=VZUhfeghjgfYUZGUIgGIZ..."
    }
    
    // Quand l'utilisateur se déconnecte // When user log out
    if(get('action') == 'logout') {
        if (!session("access_token") && !isset($_COOKIE['refresh_token'])) {
            header("Location:https://www.example.com/discord/");
        }
        
        if (session("access_token")) unset($_SESSION['access_token']);
        if (isset($_COOKIE['refresh_token'])) setcookie("refresh_token", "", time() - 3600, "", "", true, true);
        
        header('Location: https://www.example.com/discord/'); // recharger la page sans "?action=logout" // Refresh page whithout "?action=logout"
    }
    
    // Quand l'utilisateur recharge sa connexion // When user refresh connection
    if (get('action') == 'refresh' && isset($_COOKIE['refresh_token'])) {
        $data = array(
            'client_id' => OAUTH2_CLIENT_ID,
            'client_secret' => OAUTH2_CLIENT_SECRET,
            'grant_type' => 'refresh_token',
            'refresh_token' => $_COOKIE['refresh_token']
        );
        $rtoken = apiRequest($tokenURL, $data, array("Content-Type: application/x-www-form-urlencoded"), true);
        
        $_SESSION['access_token'] = $rtoken->access_token;
        setcookie("refresh_token", $rtoken->refresh_token, time()+60*60*24*30, "", "", true, true);
        
        header('Location:https://www.example.com/discord/'); // Recharger la page sans "?action=refresh" // Refresh page without "?action=refresh"
    }
    
    // ==================================================
    // =================== IF SESSION ===================
    // ==================================================
    
    if(session('access_token')) {
        // Si l'utilisateur est connecté // If user is logged in
        
        $user = apiRequest($apiURLBase); // Récup ses informations // Get his data
    
        echo '<h3>Connecté</h3> <a href="?action=logout">Déconnexion</a>';
        echo '<h4>Bienvenue, ' . $user->username . '#' . $user->discriminator . '</h4>';
    
    } else if (isset($_COOKIE['refresh_token'])) {
        // Si l'utilisateur n'est pas connecté mais qu'il y a le cookie refresh_token // If user is logged out but there is the refresh_token cookie
        
        echo '<h3>Vous avez été déconnecté !</h3> <a href="?action=refresh">Se reconnecter</a><br><a href="?action=logout">Se déconnecter</a>';
        
    } else {
        // Si l'utilisateur n'est pas connecté // If user is not logged in
        
        echo '<h3>Déconnecté</h3>';
        echo '<p><a href="?action=login">Se connecter</a></p>';
    }
    
    // ==============================================
    // =================== CONFIG ===================
    // ==============================================
    
    // Faire une requête à l'api Discord // Do a request to Discord API
    function apiRequest($url, $post=FALSE, $headers=array()) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    
        $response = curl_exec($ch);
    
    
        if($post) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        
        $headers[] = 'Accept: application/json';
    
        if(session('access_token'))
            $headers[] = 'Authorization: Bearer ' . session('access_token');
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $response = curl_exec($ch);
        return json_decode($response);
    }
    
    function get($key, $default=NULL) {
        return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
    }
    
    function session($key, $default=NULL) {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }
