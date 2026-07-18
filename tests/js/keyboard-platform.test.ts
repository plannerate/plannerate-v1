/**
 * Testes dos helpers cross-OS de atalho de teclado (interactions/keyboardPlatform.ts).
 *
 * Foco: `isDeleteShortcut` é pura e recebe `isMac` explícito — cobre o bug do Mac,
 * onde a tecla "delete" do notebook emite Backspace (sem forward-Delete).
 */

import { describe, expect, it } from 'vitest';
import { isDeleteShortcut } from '@/composables/plannerate/interactions/keyboardPlatform';

const key = (k: string) => ({ key: k }) as KeyboardEvent;

describe('isDeleteShortcut', () => {
    it('Delete deleta em qualquer OS', () => {
        expect(isDeleteShortcut(key('Delete'), true)).toBe(true);
        expect(isDeleteShortcut(key('Delete'), false)).toBe(true);
    });

    it('Backspace deleta SÓ no Mac', () => {
        // Mac: a tecla "delete" do MacBook emite Backspace → deve deletar.
        expect(isDeleteShortcut(key('Backspace'), true)).toBe(true);
        // Windows/Linux: têm tecla Delete dedicada, Backspace não deleta.
        expect(isDeleteShortcut(key('Backspace'), false)).toBe(false);
    });

    it('outras teclas nunca deletam', () => {
        for (const k of ['a', 'Enter', 'z', 'ArrowUp', ' ']) {
            expect(isDeleteShortcut(key(k), true)).toBe(false);
            expect(isDeleteShortcut(key(k), false)).toBe(false);
        }
    });
});
