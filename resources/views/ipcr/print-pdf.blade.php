<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print IPCR</title>
    <style>
        html, body { margin: 0; height: 100%; overflow: hidden; background: #fff; }
        embed { display: block; width: 100%; height: 100%; }
    </style>
</head>
<body>
<embed
    id="ipcr-pdf"
    type="application/pdf"
    src="data:application/pdf;base64,{{ $pdfBase64 }}"
    width="100%"
    height="100%"
/>
<script>
(function () {
    var printed = false;

    function triggerPrint() {
        if (printed) {
            return;
        }
        printed = true;
        setTimeout(function () {
            window.focus();
            window.print();
        }, 800);
    }

    window.addEventListener('load', triggerPrint);
    document.getElementById('ipcr-pdf').addEventListener('load', triggerPrint);

    window.addEventListener('afterprint', function () {
        if (window.frameElement && window.parent !== window) {
            return;
        }
        if (window.history.length > 1) {
            window.history.back();
        }
    });
})();
</script>
</body>
</html>
