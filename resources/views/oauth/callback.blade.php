<html>

<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>

    <script>
        // window.opener.postMessage({
        //     token: "{{ $token }}"
        // }, "{{ url('/') }}")
        // window.close()

        window.opener.postMessage({
            token: "{{ $token }}"
        }, "*");
        window.close()

        // setTimeout(() => {
        //     window.opener.postMessage({
        //         token: "{{ $token }}"
        //     }, "{{ url('/') }}");
        //     window.close();
        // }, 1000); // 1 second delay
    </script>
</head>

<body>
</body>

</html>
