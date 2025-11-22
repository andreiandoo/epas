<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div {{ $attributes->merge($getExtraAttributes())->class(['fi-fo-translatable']) }}>
        @foreach ($getChildComponents() as $childComponent)
            {{ $childComponent }}
        @endforeach
    </div>
</x-dynamic-component>
