<script>
    (function(){
        let iframe = document.createElement('iframe');
        iframe.setAttribute('src', '{{$url}}');
        iframe.setAttribute('width', '100%');
        iframe.setAttribute('height', '500px');
        iframe.setAttribute('frameBorder', 0);
        let body = document.getElementsByTagName('body')[0];
        body.append(iframe);
    }).call();
</script>
