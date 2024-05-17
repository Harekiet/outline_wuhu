<?php

// https://oauth2-client.thephpleague.com/usage/
//Include ouath2 league client from the docker php image

include "/oauth/vendor/autoload.php";

$provider = new \League\OAuth2\Client\Provider\GenericProvider([
'clientId'                => '',    // The client ID assigned to you by the provider
'clientSecret'            => '',    // The client password assigned to you by the provider
'redirectUri'             => 'https://party.outlinedemoparty.nl/index.php?page=Login',	// The Site to redirect to once logged in
'urlAuthorize'            => 'https://id.scene.org/oauth/authorize/?scope=basic user:email',
'urlAccessToken'          => 'https://id.scene.org/oauth/token/',
'urlResourceOwnerDetails' => 'https://id.scene.org/oauth/tokeninfo/',
]);

$urlMe = 'https://id.scene.org/api/3.0/me/';

// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {


    if ($_GET["sceneid"]) {


        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl();

        // Get the state generated for you and store it to the session.
        $_SESSION['oauth2state'] = $provider->getState();

        // Redirect the user to the authorization URL.
        header('Location: ' . $authorizationUrl);
        exit;
    } else {
        // no sceneid login, ignore
    }

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

// Access Token: e94ea7d9f3a2735e24d842444947a4daf548df20
// Refresh Token: 0fd5931c0d7edeaa68806db652ff70ddda53ed08
// Expired in: 1645094672
// Already expired? not expired
// array ( 'access_token' => 'e94ea7d9f3a2735e24d842444947a4daf548df20', 'client_id' => 'scenept', 'user_id' => '104295', 'expires' => 1645094672, 'scope' => 'basic', )

        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.
        // echo 'Access Token: ' . $accessToken->getToken() . "<br>";
        // echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br>";
        // echo 'Expired in: ' . $accessToken->getExpires() . "<br>";
        // echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br>";

        // Using the access token, we may look up details about the
        // resource owner.
        $resourceOwner = $provider->getResourceOwner($accessToken);

        // var_export($resourceOwner->toArray());

        // The provider provides a way to get an authenticated API request for
        // the service, using the access token; it returns an object conforming
        // to Psr\Http\Message\RequestInterface.
        $request = $provider->getAuthenticatedRequest(
            'GET',
            $urlMe,
            $accessToken
        );

        $client = new \GuzzleHttp\Client();

        // var_dump($request);
        $response = $client->send($request);
        // var_dump($response);

        // echo $response->getStatusCode(); // 200
        // echo "<br/>";
        // echo $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
        // echo "<br/>BODY";
        // echo $response->getBody(); // '{"id": 1420053, "name": "guzzle", ...}'

        $body = json_decode($response->getBody());
        // echo "<br/>BODY2:";
        // var_dump($body);
        $user = $body->user;

        // var_dump($user["email"]);
        // /var_dxump($user->email);
        // exit;

        /*
         array ( 'access_token' => 'bd2f49df2280df5ea792d49f971c2a519c84188e',
         'client_id' => 'scenept',
         'user_id' => '104295',
         'expires' => 1645095704,
         'scope' => 'basic user:email', )200application/json; charset=utf-8
         {"success":true,"user":
            {"id":104295,"first_name":"Pedro","last_name":"Cardoso","display_name":"garfield","email":"pedro@grok.pt"}}
         */

        $userID = SQLLib::selectRow(sprintf_esc("select id from users where `username`='%s' and `password`='%s' and `remote`='1'",
            $user->email,
            hashPassword($user->id)))->id;

        run_hook("login_authenticate",array("userID"=>&$userID));

        echo $userID;

          if ($userID) {
            $_SESSION["logindata"] = SQLLib::selectRow(sprintf_esc("select * from users where id=%d",$userID));
            header( "Location: ".build_url("News",array("login"=>"success")) );
        } else {
            // header( "Location: ".build_url("Login",array("login"=>"failure")) );

            $userdata = array(
                "username" => $user->email,
                "password" => hashPassword($user->id),
                "nickname" => $user->display_name,
                "group"=> "",
                "regip"=> ($_SERVER["REMOTE_ADDR"]),
                "regtime"=> (date("Y-m-d H:i:s")),
                "remote" => 1,
            );
            $error = "";
            run_hook("register_processdata",array("data"=>&$userdata));
            if (!$error)
            {
                $trans = new SQLTrans();
                $userID = SQLLib::InsertRow("users",$userdata);
                // SQLLib::UpdateRow("votekeys",array("userid"=>$userID),sprintf_esc("`votekey`='%s'",sanitize_votekey($_POST["votekey"])));
                echo "<div class='success'>Registration successful!</div>";

                $_SESSION["logindata"] = SQLLib::selectRow(sprintf_esc("select * from users where id=%d",$userID));
                header( "Location: ".build_url("News",array("login"=>"success")) );

            } else {
                echo "<div class='failure'>"._html($error)."</div>";
            }

        }
        exit();

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        // Failed to get the access token or user details.
        exit($e->getMessage());
    } catch (Exception $e) {
        exit($e->getMessage());
        var_dump($e);
    }

}
