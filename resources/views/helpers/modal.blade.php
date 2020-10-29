<!-- Button trigger modal -->
{{--<button type="button" class="btn btn-primary m-1" data-toggle="modal" data-target="#{!! $id !!}">--}}
{{--    {{$label}}--}}
{{--</button>--}}

<!-- Modal -->
<div class="modal fade" id="{!! $id !!}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">Modal title</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{$slot}}
            </div>
            <div class="modal-footer">
                {{isset($action) ? $action : ''}}
            </div>
        </div>
    </div>
</div>
