<template data-template="tableRow">
    <tr data-code = "${shippingMethodCode}">
        <td>
            <input type ="hidden" name ="advanced_shipping[${shippingMethodCode}][code]" value ="${shippingMethodCode}">
            <input type ="hidden" name ="advanced_shipping[${shippingMethodCode}][name]" value ="${name}">
            ${name}
        </td>
        <td>
            <a href = "#" class = "button closed settings_button" data-container=".price_settings_container">{{trans('app.settings.additional.price_settings')}}</a>
            <a href = "#" class = "button closed settings_button" data-container=".visibility_settings_container">{{trans('app.settings.additional.visibility_settings')}}</a>
            <a href = "#" class = "button closed settings_button" data-container=".service_settings_container">{{trans('app.settings.additional.service_settings')}}</a>
            <div class="row price_settings_container mt-20 settings_container" style = "display: none;">
                <div class ="row">
                    <div class="input-group">
                        <div class = "append">{{trans('app.settings.additional.price_calculation')}}</div>
                            <select name="advanced_shipping[${shippingMethodCode}][price_calc]" class = "price_calc" data-code = "${shippingMethodCode}">
                                @foreach ($price_calcs as $val => $title)
                                    <option value ="{{$val}}">{{$title}}</option>
                                @endforeach
                            </select>
                    </div>
                </div>
                <div class = "price_container">
                </div>
            </div>  
            <div class="row visibility_settings_container mt-20 settings_container" style = "display: none;">
                <div class = "visibility_container">
                </div>
                <a href ="#" class = "button add_visibility_condition">+</a>
            </div>  
            <div class="row service_settings_container mt-20 settings_container" style = "display: none;">
                <div class = "service_container">
                </div>
            </div>  
         </td>
         <td><a href ="#" class = "button warning remove_shipping_method">{{trans('app.settings.additional.remove')}}</a></td>
    </tr>
</template>

<template data-template="priceValue">
    <div class="input-group">
        <input type="number" min="0" name="advanced_shipping[${shippingMethodCode}][price_value]" value="${value}">
        <div class = "append">${type}</div>
    </div>
</template>

<template data-template="priceRangeContainer">
    <div class = "price_range_container"></div>
    <a href ="#" class = "button add_price_condition">+</a>
</template>

<template data-template="priceRangeRow">
    <div class="row priceRangeRow mb-10">
        <div class="columns ten">
            <div class="input-group">
                <div class = "append">{{trans('app.settings.additional.from')}}, ${type}</div>
                <input type="number" min="0" name="advanced_shipping[${shippingMethodCode}][price_conditions][from][]" value="${from}">
                <div class = "append">{{trans('app.settings.additional.to')}}, ${type}</div>
                <input type="number" min="0" name="advanced_shipping[${shippingMethodCode}][price_conditions][to][]" value="${to}">
                <div class = "append">{{trans('app.settings.additional.price')}}</div>
                <input type="number" min="0" name="advanced_shipping[${shippingMethodCode}][price_conditions][value][]" value="${value}">
            </div>  
        </div>
        <div class="columns two">
            <a href ="#" class = "button warning remove_price_condition">X</a>
        </div>
    </div>
</template>

<template data-template="visibilityRow">
    <div class="row visibilityRow mb-10">
        <div class="columns ten">
            <div class="input-group">
                <div class = "append">{{trans('app.settings.additional.condition')}}</div>
                <select name="advanced_shipping[${shippingMethodCode}][visibility_conditions][condition][]" class = "condition">
                    @foreach ($visibility_params['conditions'] as $val => $title)
                        <option value ="{{$val}}">{{$title}}</option>
                    @endforeach
                </select>
                <div class = "append">{{trans('app.settings.additional.field')}}</div>
                <select name="advanced_shipping[${shippingMethodCode}][visibility_conditions][field][]" class = "field">
                    @foreach ($visibility_params['fields'] as $val => $title)
                        <option value ="{{$val}}">{{$title}}</option>
                    @endforeach
                </select>
                <div class = "append">{{trans('app.settings.additional.value')}}</div>
                <input type="text" name="advanced_shipping[${shippingMethodCode}][visibility_conditions][value][]" class = "value">
            </div>  
        </div>
        <div class="columns two">
            <a href ="#" class = "button warning remove_visibility_condition">X</a>
        </div>
    </div>
</template>

<template data-template="serviceItem">
    <label>
        <input type="hidden" name="advanced_shipping[${shippingMethodCode}][all_services][${code}]" value="${name}">
        <input type="checkbox" name="advanced_shipping[${shippingMethodCode}][selected_services][]" value="${code}">
        ${name}
    </label>
</template>

<script>
    var settings_json = '{!! json_encode($shipping_settings) !!}';
    var row_template = $('template[data-template="tableRow"]').html().split(/\$\{(.+?)\}/g);
    var price_value_template = $('template[data-template="priceValue"]').html().split(/\$\{(.+?)\}/g);
    var price_range_template = $('template[data-template="priceRangeContainer"]').html().split(/\$\{(.+?)\}/g);
    var price_range_row_template = $('template[data-template="priceRangeRow"]').html().split(/\$\{(.+?)\}/g);
    var visibility_row_template = $('template[data-template="visibilityRow"]').html().split(/\$\{(.+?)\}/g);
    var service_row_template = $('template[data-template="serviceItem"]').html().split(/\$\{(.+?)\}/g);
    
    var items = $.parseJSON(settings_json);
        $('body').off('click', '.add_shipping_method');
        $('body').on('click', '.add_shipping_method', function(e){
            e.preventDefault();
            var $select = $(this).parent('.input-group').find('select');
            if ($select.val()) {
                var data = {name: $('option:selected', $select).attr('data-name'), shippingMethodCode: $select.val()};
                var row = $(row_template.map(render(data)).join(''));
                addServices($.parseJSON($('option:selected', $select).attr('data-services')), row.find('.service_container'), $select.val());
                $('table.advanced_shipping_table tbody').append( row );
                row.find('select.price_calc').trigger('change');
            }
        });
        $('body').off('click', '.remove_shipping_method');
        $('body').on('click', '.remove_shipping_method', function(e){
            e.preventDefault();
            $(this).parents('tr').fadeOut(function(){
                $(this).remove();
            });
        });
        $('body').off('change', '.price_calc');
        $('body').on('change', '.price_calc', function(){
            var container = $(this).parents('.price_settings_container').find('.price_container');
            if ($(this).val() === 'fixed') {
                var data = {shippingMethodCode: $(this).attr('data-code'), type: 'Eur'};
                container.html(price_value_template.map(render(data)).join(''));
            }
            if ($(this).val() === 'percent') {
                var data = {shippingMethodCode: $(this).attr('data-code'), type: '%'};
                container.html(price_value_template.map(render(data)).join(''));
            }
            if ($(this).val() === 'cart_based') {
                var inner_container = $(price_range_template.map(render({type: 'Eur'})).join(''));
                container.html('').append(inner_container);
            }
            if ($(this).val() === 'weight_based') {
                var inner_container = $(price_range_template.map(render({type: 'kg'})).join(''));
                container.html('').append(inner_container);
            }
        });
        $('body').off('click', '.add_price_condition');
        $('body').on('click', '.add_price_condition', function(e){
            e.preventDefault();
            var code =  $(this).parents('tr').attr('data-code');
            var price_type = $(this).parents('tr').find('.price_calc').val();
            if (price_type === 'cart_based') {
                var type = "Eur";
            } else {
                var type = "kg";
            }
            var data = {from: '', to: '', shippingMethodCode: code, value: '', type: type};
            var inner_container = $(this).parents('.price_settings_container').find('.price_range_container');
            inner_container.append($(price_range_row_template.map(render(data)).join('')));
        });
        $('body').off('click', '.remove_price_condition');
        $('body').on('click', '.remove_price_condition', function(e){
            e.preventDefault();
            $(this).parents('.priceRangeRow').fadeOut(function(){
                $(this).remove();
            });
        });
        $('body').off('click', '.settings_button');
        $('body').on('click', '.settings_button', function(e){
            e.preventDefault();
            let td = $(this).parent('td');
            let container_el = td.find($(this).attr('data-container'));
            if ($(this).hasClass('closed')) {
                $(this).toggleClass('closed');
                container_el.slideDown();
            } else {
                $(this).toggleClass('closed');
                container_el.slideUp();
            }
            td.find('.settings_button').not($(this)).addClass('closed');
            td.find('.settings_container').not(container_el).slideUp();
            
        });
        
        
        $('body').off('click', '.add_visibility_condition');
        $('body').on('click', '.add_visibility_condition', function(e){
            e.preventDefault();
            var code =  $(this).parents('tr').attr('data-code');
            var data = {shippingMethodCode: code};
            var inner_container = $(this).parents('.visibility_settings_container').find('.visibility_container');
            inner_container.append($(visibility_row_template.map(render(data)).join('')));
        });
        
        $('body').off('click', '.remove_visibility_condition');
        $('body').on('click', '.remove_visibility_condition', function(e){
            e.preventDefault();
            $(this).parents('.visibilityRow').fadeOut(function(){
                $(this).remove();
            });
        });
        
    $.each(items, function(code, item){
        try {
            var data = {name: item.name, shippingMethodCode: code};
            var row = $(row_template.map(render(data)).join(''));
            //select price method
            row.find('.price_calc').val(item.price_calc);

            var container = row.find('.price_container');
            if (item.price_calc === 'fixed') {
                    var data = {shippingMethodCode: code, value: item.price_value, type: 'Eur'};
                    container.html(price_value_template.map(render(data)).join(''));
                }
                if (item.price_calc === 'percent') {
                    var data = {shippingMethodCode: code, value: item.price_value, type: '%'};
                    container.html(price_value_template.map(render(data)).join(''));
                }
                if (item.price_calc === 'cart_based' || item.price_calc === 'weight_based') {
                    var inner_container = $(price_range_template.map(render({})).join(''));
                    if (item.price_calc === 'cart_based'){ 
                        var type = 'Eur';
                    } else if (item.price_calc === 'weight_based'){ 
                        var type = 'kg';
                    }
                    container.html('').append(inner_container);
                    
                    if (item.price_conditions !== undefined) {
                        for (let i = 0; i < item.price_conditions.value.length; i++) {
                            var data = {from: item.price_conditions.from[i], to: item.price_conditions.to[i], shippingMethodCode: code, value: item.price_conditions.value[i], type: type};
                            container.find('.price_range_container').append($(price_range_row_template.map(render(data)).join('')));
                        }
                    }
                    
                }
                //add visibility conditions
                if (item.visibility_conditions !== undefined) {
                    for (let i = 0; i < item.visibility_conditions.value.length; i++) {
                        var data = {shippingMethodCode: code};
                        let condition_row = $(visibility_row_template.map(render(data)).join(''));
                        row.find('.visibility_container').append(condition_row);
                        condition_row.find('select.condition').val(item.visibility_conditions.condition[i]);
                        condition_row.find('select.field').val(item.visibility_conditions.field[i]);
                        condition_row.find('input.value').val(item.visibility_conditions.value[i]);
                    }
                }
                
                //add services
                $.each(item.all_services, function(service_code, name){
                    var inner_container = $(service_row_template.map(render({name: name, code: service_code, shippingMethodCode: code})).join(''));
                    row.find('.service_container').append(inner_container);
                    if ($.inArray(service_code, item.selected_services) !== -1) {
                        inner_container.find('input[type="checkbox"]').prop('checked', true);
                    }
                });

            $('table.advanced_shipping_table tbody').append( row );
        } catch(err) {
            console.log(err.message);
        }
    });
    
    function addServices(services, container, method_code){
        $.each(services, function(index, service){
            if (service.specifiers === null) {
                var inner_container = $(service_row_template.map(render({name: service.name, code: service.service_code, shippingMethodCode: method_code })).join(''));
                container.append(inner_container);
            }
        });
    }
    
    function render(props) {
        return function(tok, i) { return (i % 2) ? props[tok] : tok; };
      }
</script>

