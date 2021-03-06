<?php
/**
 * Bluem Integration - Magento2 Module
 * (C) Bluem 2021
 *
 * @category Module
 * @author   Daan Rijpkema <d.rijpkema@bluem.nl>
 *
 */

namespace Bluem\Integration\Controller\Identity;

use Bluem\Integration\Controller\BluemAction;

require_once __DIR__ . '/../BluemAction.php';

class Request extends BluemAction
{
    public function execute()
    {
        $debug = false;

        $scenario = $this->_dataHelper->getIdentityConfig('identity_scenario');
        $requestCategories = $this->_dataHelper->getIdentityRequestCategories();

        // validate:
        // mandatory: if user is logged in (constraint for now)
        // optional: if user is not already verified?
        // Make a mention they can return to the previous page, create a view?

        // provide a link here to the callback function; either in this script or another script
        $returnURL = $this->_baseURL . "/bluem/identity/response/";

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        $ip = $remote->getRemoteAddress();

        $payload = [
            'usecase'=>$scenario,
            'categories'=>$requestCategories,
            'ip'=>$ip,
            'userdata'=>[]
        ];


        if ($this->_customerSession->isLoggedIn()) {
            $email = $this->_customerSession->getCustomer()->getEmail();
            $name = $this->_customerSession->getCustomer()->getName();
            $id = $this->_customerSession->getCustomer()->getId();

            $payload['userdata'] = [
                'email'=> $email,
                'name'=> $name,
                'id'  => $id
            ];
            
            // description is shown to customer
            $description = "Verificatie $name (klantnummer $id)";
            // client reference/number
            $debtorReference = "$id";

        
        } else {
            // guest user payload

            $payload['userdata'] = [];
            $description = "Verificatie identiteit";
            
            $debtorReference = "Gastidentificatie";
        }

        $request_data = [
            'Type'=>"identity",
            'Description'=>$description,
            'DebtorReference'=>$debtorReference,
            'ReturnUrl'=>$returnURL,
            'Payload'=>json_encode($payload),
            'Status'=>"created"
        ];

        $request_db_id = parent::_createRequest(
            $request_data
        );

        // append created requestID to the URL to return to
        $returnURL .= "requestId/{$request_db_id}";

        $request = $this->_bluem->CreateIdentityRequest(
            $requestCategories,
            $description,
            $debtorReference,
            $returnURL
        );

        $bluem_env = $this->_dataHelper->getGeneralConfig('environment');
        if ($bluem_env === "test") {
            $request->enableStatusGUI();
        }
        
        $response = $this->_bluem->PerformRequest($request);

        if ($response->ReceivedResponse()) {
            $entranceCode = $response->getEntranceCode();
            $transactionID = $response->getTransactionID();
            $transactionURL = $response->getTransactionURL();

            if ($debug) {
                echo "entranceCode: {$entranceCode}<br>";
                echo "transactionID: {$transactionID}<br>";
            }
            // todo: add ageverify type
            // save this somewhere in your data store
            $update_data = [
                'EntranceCode'=>$entranceCode,
                'TransactionId'=>$transactionID,
                'TransactionUrl'=>$transactionURL,
                'ReturnUrl'=>$returnURL, // also updated this
                'Status'=>"requested"
            ];
            $this->_updateRequest($request_db_id, $update_data);

            // direct the user to this place
            header("Location: ".$transactionURL);
            exit;
        } else {
            // no proper response received, tell the user:
            echo " Invalid response received, please contact your webshop administrator";
            echo "<br> More details: <br>";
            var_dump($response);
            exit;
        }
        exit;
    }
}
