

    <section>
        <div class="column" style="margin-top: 2rem;">
            @yield('card-content')
        </div>
    </section>
    <footer>
        <article class="help">
            <span></span>
            <p>{!! trans('app.settings.instructions', ['company_name' => trans('app.settings.company_name'), 'instruction_url' => '#']) !!}</p>
        </article>
    </footer>


