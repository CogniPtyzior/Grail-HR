<!-- Generic editable, collapsible list section used by the profile detail page for structured CV analysis arrays. -->
<script setup lang="ts">
import { ChevronDown, Plus, Trash2 } from 'lucide-vue-next';

interface FieldDefinition {
  key: string;
  label: string;
  kind?: 'text' | 'textarea';
}

const props = withDefaults(defineProps<{
  title: string;
  description: string;
  items: Record<string, unknown>[];
  fields: FieldDefinition[];
  defaultOpen?: boolean;
}>(), {
  defaultOpen: true,
});

const emit = defineEmits<{ changed: []; 'update:items': [items: Record<string, unknown>[]] }>();
const isOpen = ref(props.defaultOpen);
const openItems = ref<Record<number, boolean>>({});

function isItemOpen(index: number): boolean {
  return openItems.value[index] ?? false;
}

function toggleItem(index: number) {
  openItems.value = { ...openItems.value, [index]: !isItemOpen(index) };
}

function addItem() {
  const item: Record<string, string> = {};
  props.fields.forEach((field) => {
    item[field.key] = '';
  });
  emit('update:items', [...props.items, item]);
  isOpen.value = true;
  openItems.value = { ...openItems.value, [props.items.length]: true };
  emit('changed');
}

function removeItem(index: number) {
  const nextOpenItems: Record<number, boolean> = {};

  Object.entries(openItems.value).forEach(([key, value]) => {
    const itemIndex = Number(key);
    if (itemIndex < index) nextOpenItems[itemIndex] = value;
    if (itemIndex > index) nextOpenItems[itemIndex - 1] = value;
  });

  openItems.value = nextOpenItems;
  emit('update:items', props.items.filter((_, itemIndex) => itemIndex !== index));
  emit('changed');
}

function updateItem(item: Record<string, unknown>, index: number, key: string, event: Event) {
  const updatedItem = { ...item, [key]: (event.target as HTMLInputElement | HTMLTextAreaElement).value };
  emit('update:items', props.items.map((currentItem, itemIndex) => itemIndex === index ? updatedItem : currentItem));
  emit('changed');
}

function fieldValue(item: Record<string, unknown>, key: string): string {
  return String(item[key] ?? '').trim();
}

function itemTitle(item: Record<string, unknown>, index: number): string {
  const preferredKeys = ['role', 'label', 'degree', 'school', 'company', 'type', 'title'];
  for (const key of preferredKeys) {
    const value = fieldValue(item, key);
    if (value) return value;
  }
  return `Élément ${index + 1}`;
}

function itemSubtitle(item: Record<string, unknown>): string {
  const parts = ['company', 'school', 'category', 'location', 'level']
    .map((key) => fieldValue(item, key))
    .filter(Boolean);
  return [...new Set(parts)].slice(0, 3).join(' · ');
}

function dateRange(item: Record<string, unknown>): string {
  const start = fieldValue(item, 'start_date');
  const end = fieldValue(item, 'end_date');
  if (!start && !end) return '';
  return `${start || 'Début non indiqué'} → ${end || 'Aujourd’hui'}`;
}
</script>

<template>
  <section class="ghr-section ghr-collapsible-section" :class="{ 'ghr-collapsible-section--closed': !isOpen }">
    <header class="ghr-section__header">
      <button
        class="ghr-section__toggle"
        type="button"
        :aria-expanded="isOpen"
        @click="isOpen = !isOpen"
      >
        <ChevronDown class="ghr-section__chevron" :size="18" aria-hidden="true" />
        <span>
          <span class="ghr-section__title-row">
            <span>{{ title }}</span>
            <span class="ghr-count-badge">{{ items.length }}</span>
          </span>
          <span class="ghr-muted">{{ description }}</span>
        </span>
      </button>
      <button class="ghr-icon-action ghr-icon-action--primary" type="button" :title="`Ajouter : ${title}`" :aria-label="`Ajouter : ${title}`" @click="addItem">
        <Plus :size="18" aria-hidden="true" />
      </button>
    </header>

    <div v-show="isOpen" class="ghr-section__body">
      <div v-if="items.length === 0" class="ghr-empty-state">Aucune donnée renseignée pour cette section.</div>
      <article
        v-for="(item, index) in items"
        :key="index"
        class="ghr-list-item ghr-edit-card"
        :class="{ 'ghr-edit-card--closed': !isItemOpen(index) }"
      >
        <header class="ghr-edit-card__header">
          <button
            class="ghr-edit-card__toggle"
            type="button"
            :aria-expanded="isItemOpen(index)"
            @click="toggleItem(index)"
          >
            <ChevronDown class="ghr-section__chevron ghr-edit-card__chevron" :size="18" aria-hidden="true" />
            <span class="ghr-edit-card__summary">
              <span class="ghr-edit-card__title">{{ itemTitle(item, index) }}</span>
              <span v-if="itemSubtitle(item) || dateRange(item)" class="ghr-muted">
                <span v-if="itemSubtitle(item)">{{ itemSubtitle(item) }}</span>
                <span v-if="itemSubtitle(item) && dateRange(item)"> · </span>
                <span v-if="dateRange(item)">{{ dateRange(item) }}</span>
              </span>
            </span>
          </button>
          <button
            class="ghr-icon-action ghr-icon-action--danger"
            type="button"
            title="Supprimer cet élément"
            aria-label="Supprimer cet élément"
            @click="removeItem(index)"
          >
            <Trash2 :size="18" aria-hidden="true" />
          </button>
        </header>

        <div v-show="isItemOpen(index)" class="ghr-list-item__fields">
          <label v-for="field in fields" :key="field.key" class="ghr-field" :class="{ 'ghr-field--wide': field.kind === 'textarea' }">
            <span>{{ field.label }}</span>
            <textarea
              v-if="field.kind === 'textarea'"
              :value="String(item[field.key] ?? '')"
              class="ghr-textarea ghr-textarea--compact"
              @input="updateItem(item, index, field.key, $event)"
            />
            <input
              v-else
              :value="String(item[field.key] ?? '')"
              class="ghr-input"
              @input="updateItem(item, index, field.key, $event)"
            >
          </label>
        </div>
      </article>
    </div>
  </section>
</template>
