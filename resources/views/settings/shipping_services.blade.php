    <article>
        <div class="card">

            <div class="row">
                <label><input type="checkbox" name="print_return_labels" @if($shop->always_create_return_label) checked @endif value="1">{{trans('app.settings.print_return_labels')}}</label>
                <label><input type="checkbox" name="create_activation_code" @if($shop->create_activation_code) checked @endif value="1">{{trans('app.settings.create_activation_code')}}</label>
                <p><small>{{trans('app.settings.create_activation_code_desc')}}</small></p>
                <label>
                    <input type="checkbox" name="add_additional_label_info" onclick ="toggle_div(this, 'addtional_label_info_row');" @if($shop->add_additional_label_info) checked @endif value="1">{{trans('app.settings.add_additional_label_info')}}
                </label>
            </div>
            <div class="row" id = "addtional_label_info_row" @if(!$shop->add_additional_label_info) style="display:none;" @endif>
                <div class="columns four rate-name-column">
                    {{trans('app.settings.additional_label_info')}}
                </div>
                <div class="columns eight">
                    <textarea name="additional_label_info" rows ="5">{{$shop->additional_label_info}}</textarea>
                    <small>
                        @foreach ($additional_info_keys as $key => $desc)
                        {{ $key }} - {{ $desc }}<br/>
                        @endforeach
                    </small>
                </div>
            </div>
        </div>

    </article>