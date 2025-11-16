# UI Components Architecture

This document explains how UI components work in this application and common pitfalls to avoid.

## Component Stack

The application uses a layered approach to UI components:

1. **Reka UI** (headless primitives) - Provides unstyled, accessible components with built-in behavior
2. **shadcn/ui** (styled wrappers) - Tailwind-styled components that wrap Reka UI primitives
3. **Application components** - Custom components in `/resources/js/components/ui/`

## Key Understanding

### Reka UI (formerly Radix Vue)

- Location: `node_modules/reka-ui`
- Purpose: Headless component library providing accessibility and behavior
- Uses `modelValue` and `update:modelValue` for controlled components
- Examples: `CheckboxRoot`, `SelectRoot`, `DialogRoot`

### shadcn/ui Wrappers

- Location: `/resources/js/components/ui/`
- Purpose: Apply Tailwind CSS styling to Reka UI primitives
- These are **copy-pasted** into the codebase (not an npm dependency)
- Components forward props to Reka UI using `useForwardPropsEmits`

## Common Pitfalls

### Checkbox Component

**Problem:** Checkboxes not reflecting initial values from props.

**Root Cause:** Reka UI's Checkbox uses `modelValue`/`update:modelValue`, NOT `checked`/`update:checked`.

**Wrong:**
```vue
<!-- This does NOT work - wrong prop name -->
<Checkbox :checked="form.is_active" @update:checked="form.is_active = $event" />

<!-- This also does NOT work - wrong v-model binding -->
<Checkbox v-model:checked="form.is_active" />

<!-- This also does NOT work - v-model without modifier doesn't work with Reka UI -->
<Checkbox v-model="form.is_active" />
```

**Correct:**
```vue
<!-- Use default-value for initial state + update:model-value for changes -->
<Checkbox
  :default-value="form.is_active"
  @update:model-value="form.is_active = $event"
/>
```

### Why This Happens

The shadcn/ui Checkbox wrapper (`/resources/js/components/ui/checkbox/Checkbox.vue`) uses `useForwardPropsEmits` to pass through all props to Reka UI's `CheckboxRoot`. However:

- Reka UI expects `modelValue` (not `checked`)
- Reka UI emits `update:modelValue` (not `update:checked`)

The wrapper doesn't remap these prop names, so you must use Reka UI's conventions.

## Best Practices

### For Boolean Form Fields (Checkboxes, Switches)

```vue
<script setup>
const form = useForm({
  is_active: props.resource.is_active,  // Boolean from backend
});
</script>

<template>
  <Checkbox
    id="is_active"
    :default-value="form.is_active"
    @update:model-value="form.is_active = $event"
  />
</template>
```

### For Selection State (Multiple Checkboxes)

When managing selection state (e.g., bulk actions with multiple checkboxes), use `:model-value` for fully controlled behavior:

```vue
<script setup>
const selectedItems = ref<number[]>([]);

const toggleSelection = (itemId: number, checked: boolean) => {
  if (checked) {
    selectedItems.value = [...selectedItems.value, itemId];
  } else {
    selectedItems.value = selectedItems.value.filter(id => id !== itemId);
  }
};

const allSelected = computed(() => {
  return items.length > 0 && selectedItems.value.length === items.length;
});

const toggleSelectAll = (checked: boolean) => {
  if (checked) {
    selectedItems.value = items.map(item => item.id);
  } else {
    selectedItems.value = [];
  }
};
</script>

<template>
  <!-- Select All Checkbox -->
  <Checkbox
    :model-value="allSelected"
    @update:model-value="(checked: boolean) => toggleSelectAll(checked)"
  />

  <!-- Individual Item Checkboxes -->
  <Checkbox
    v-for="item in items"
    :key="item.id"
    :model-value="selectedItems.includes(item.id)"
    @update:model-value="(checked: boolean) => toggleSelection(item.id, checked)"
  />
</template>
```

**Key differences from form fields:**
- Use `:model-value` (not `:default-value`) for reactive computed values
- Use arrow functions with explicit `boolean` type annotation in the template
- Replace arrays immutably (`[...array, item]`) to ensure Vue reactivity

### For Select Components

```vue
<Select v-model="form.type">
  <SelectTrigger>
    <SelectValue placeholder="Select type" />
  </SelectTrigger>
  <SelectContent>
    <SelectItem v-for="item in items" :key="item.value" :value="item.value">
      {{ item.label }}
    </SelectItem>
  </SelectContent>
</Select>
```

### For Text Inputs

```vue
<Input v-model="form.name" type="text" />
```

## Debugging UI Component Issues

1. **Check the data types** - Add debug output to verify props are arriving correctly:

   ```vue
   <div>{{ typeof props.value }} - {{ props.value }}</div>
   ```

2. **Check Reka UI docs** - When shadcn/ui component behavior is unclear, consult Reka UI documentation since that's the underlying engine.

3. **Look at working examples** - Search the codebase for similar components that work correctly.

4. **Verify backend casting** - Ensure Laravel model casts are correct:

   ```php
   protected function casts(): array
   {
       return [
           'is_active' => 'boolean',
       ];
   }
   ```

5. **Check serialization** - When passing collections to frontend, use `->toArray()`:

   ```php
   'tag_ids' => $source->tags->pluck('id')->toArray(),  // Array, not Collection
   ```

## Build Process

After making frontend changes, rebuild assets:

```bash
npm run build
```

Or during development:

```bash
npm run dev
```

## References

- [Reka UI Documentation](https://reka-ui.com/)
- [shadcn/ui Vue Documentation](https://www.shadcn-vue.com/)
- [Inertia.js Vue Forms](https://inertiajs.com/forms)
