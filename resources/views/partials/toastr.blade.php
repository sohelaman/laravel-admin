@if(Session::has('toastr'))
    @php
        $toastr     = admin_message_bag(Session::pull('toastr'));
        $type       = \Illuminate\Support\Arr::get($toastr->get('type'), 0, 'success');
        $type       = in_array($type, ['success', 'info', 'warning', 'error'], true) ? $type : 'success';
        $message    = \Illuminate\Support\Arr::get($toastr->get('message'), 0, '');
        $options    = json_encode($toastr->get('options', []));
    @endphp
    <script>
        $(function () {
            toastr.{{$type}}(@json($message), null, {!! $options !!});
        });
    </script>
@endif