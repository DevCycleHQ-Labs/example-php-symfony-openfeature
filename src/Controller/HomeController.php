<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DevCycle\DevCycleConfiguration;
use DevCycle\Api\DevCycleClient;
use DevCycle\Model\DevCycleOptions;
use DevCycle\Model\DevCycleUser;
use GuzzleHttp\Client;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;

class HomeController extends AbstractController
{
    #[Route('/')]
    public function index(): Response
    {

        #--------------------------------------------------------------------
        # Establish a connection to DevCycle
        #--------------------------------------------------------------------

        // Create a new DevCycleOptions object, enabling debug mode or additional logging if true is passed.
        $options = new DevCycleOptions(true);


        // Initialize the DevCycle client with the server SDK key obtained from environment variables and the previously defined options.
        // This client will interact with the DevCycle API for feature flag evaluations.
        $devcycle_client = new DevCycleClient(
            sdkKey: $_ENV["DEVCYCLE_SERVER_SDK_KEY"],
            dvcOptions: $options
        );

        // Obtain an instance of the OpenFeature API. This is a singleton instance used across the application.
        $api = OpenFeatureAPI::getInstance();


        // Set the feature flag provider for OpenFeature to be the provider obtained from the DevCycle client.
        // This integrates DevCycle with OpenFeature, allowing OpenFeature to use DevCycle for flag evaluations.
        $api->setProvider($devcycle_client->getOpenFeatureProvider());

        // Retrieve the OpenFeature client from the API instance. This client can be used to evaluate feature flags using the OpenFeature API.
        $openfeature_client = $api->getClient();

        // Create a new DevCycleUser object with the specified user ID. This object represents a user for whom feature flags will be evaluated.
        $devcycle_user_data = new DevCycleUser(array(
            "user_id" => "my-user"
        ));

        // Instantiate a new EvaluationContext object with 'devcycle_user_data' as its parameter. However, this seems to be a misuse or a typo,
        // as typically, the EvaluationContext should be instantiated with an array or similar structure representing the context, not a string.
        // If 'devcycle_user_data' is intended to be used as context, it should be passed directly as an object or its data extracted into an array,
        // not passed as a string literal.
        $openfeature_context = new EvaluationContext('devcycle_user_data');
        #--------------------------------------------------------------------
        # Variables used in togglebot.html.twig
        #--------------------------------------------------------------------

        // Fetch all feature flags for the specified DevCycle user. The result is an associative array where keys are feature flag keys and values are their details.
        $features = $devcycle_client->allFeatures($devcycle_user_data);

        // Determine the variation name for the "hello-togglebot" feature flag. If the flag is present, use its variation name; otherwise, default to "Default".
        $variation_name = $features["hello-togglebot"] ? $features["hello-togglebot"]["variationName"] : "Default";

        // Use the OpenFeature client to get the string value of the "togglebot-speed" feature flag. If the flag is not set, default to "off".
        // The evaluation context (user or environment details) is passed to refine the flag evaluation.
        $speed = $openfeature_client->getStringValue("togglebot-speed", "off", $openfeature_context);

        // Use the OpenFeature client to get the boolean value of the "togglebot-wink" feature flag. If the flag is not set, default to false.
        $wink = $openfeature_client->getBooleanValue("togglebot-wink", false, $openfeature_context);



        // Based on the value of the "togglebot-speed" feature flag, set a corresponding message.
        switch ($speed) {
            case 'slow': // If the speed is set to "slow", set a specific message.
                $message = 'Awesome, look at you go!';
                break;
            case 'fast': // If the speed is set to "fast", set a different message.
                $message = 'This is fun!';
                break;
            case 'off-axis': // If the speed is set to "off-axis", set a message indicating discomfort.
                $message = '...I\'m gonna be sick...';
                break;
            case 'surprise': // If the speed is set to "surprise", set a surprising message.
                $message = 'What the unicorn?';
                break;
            default: // For any other value (including "off"), set a default greeting message.
                $message = 'Hello! Nice to meet you.';
                break;
        }


        #--------------------------------------------------------------------
        # Variables used in description.html.twig
        #--------------------------------------------------------------------


        // Retrieve the value of the "example-text" feature flag using the OpenFeature client.
        // The default value is "default" if the flag is not set or cannot be fetched.
        $step = $openfeature_client->getStringValue("example-text", "default", $openfeature_context);


        // Based on the value of the "example-text" feature flag, adjust the content of the header and body variables accordingly.
        switch ($step) {
            case "step-1": // If the flag's value is "step-1", set the header and body for the first step in the onboarding process.
                $header = "Welcome to DevCycle's example app.";
                $body = "If you got here through the onboarding flow, just follow the instructions to change and create new Variations and see how the app reacts to new Variable values.";
                break;
            case "step-2": // If the flag's value is "step-2", provide information relevant to the second step in the onboarding.
                $header = "Great! You've taken the first step in exploring DevCycle.";
                $body = "You've successfully toggled your very first Variation. You are now serving a different value to your users and you can see how the example app has reacted to this change. Next, go ahead and create a whole new Variation to see what else is possible in this app.";
                break;
            case "step-3": // If the flag's value is "step-3", congratulate the user on progressing and encourage further exploration.
                $header = "You're getting the hang of things.";
                $body = "By creating a new Variation with new Variable values and toggling it on for all users, you've already explored the fundamental concepts within DevCycle. There's still so much more to the platform, so go ahead and complete the onboarding flow and play around with the feature that controls this example in your dashboard.";
                break;
            default: // For any other value, provide a default welcome message and guidance for newcomers.
                $header = "Welcome to DevCycle's example app.";
                $body = "If you got to the example app on your own, follow our README guide to create the Feature and Variables you need to control this app in DevCycle.";
        }

        // The resulting $header and $body variables contain text that can be dynamically inserted into the webpage or app view,
        // allowing the content to adapt based on the progression of the user through the onboarding process or their interaction with the example application.



        // Determine the source of the togglebot image. If the "togglebot-wink" flag is true, use the winking image; otherwise, use the default image.
        $togglebot_src = $wink ? '/assets/img/togglebot-wink.png' : '/assets/img/togglebot.png';

        // If the speed feature flag is set to "surprise", override the togglebot image source with a unicorn image.
        if ($speed === 'surprise') {
            $togglebot_src = '/assets/img/unicorn.svg';
        }

        return $this->render('home.html.twig', ['devcycle_client' => $devcycle_client, 'devcycle_user_data' => $devcycle_user_data, 'variation_name' => $variation_name, 'message' => $message, 'togglebot_src' => $togglebot_src, 'header' => $header, 'body' => $body, 'step' => $step, 'features' => $features, 'speed' => $speed, 'wink' => $wink, 'openfeature_context' => $openfeature_context, 'openfeature_client' => $openfeature_client]);
    }
}
