<div class="buttons">
    <div class="center">
        <button id="payment-button" class="btn lightgreen_gradient">Pay with Khalti</button>
    </div>
</div>

<script>
    var base_url = window.location.origin+'/rollingnexus';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('input[name=\'_token\']').val()
        }
    });
    var config = {
        "publicKey": "{{$data['publicKey']}}" ,
        "productIdentity": "{{$data['productIdentity']}}",
        "productName": "{{$data['productName']}}",
        "productUrl": "{{$data['productUrl']}}",
        "paymentPreference": [
            "MOBILE_BANKING",
            "KHALTI",
            "EBANKING",
            "CONNECT_IPS",
            "SCT",
        ],
        "eventHandler": {
            onSuccess (payload) {
                // hit merchant api for initiating verfication
                console.log(payload);
                $("#loader").css({'visibility':'visible'});
                $("#payment-button").css({'visibility':'hidden'});
                $.ajax({
                    type:'POST',
                    url: base_url + '/employer/booth/khalti/verify',
                    data:payload,
                    success:function(data){
                        location.replace(base_url + "/employer/enroll/report");
                    }
                });
            },
            onError (error) {
                alert("Something went wrong.");
                console.log(error);
            },
            onClose () {
                console.log('widget is closing');
            }
        }
    };

    var checkout = new KhaltiCheckout(config);
    var btn = document.getElementById("payment-button");
    let amount = {{$data['amount']}}*100;
    btn.onclick = function () {
        // minimum transaction amount must be 10, i.e 1000 in paisa.
        checkout.show({amount});
    }
</script>
