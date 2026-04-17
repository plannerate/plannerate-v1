<?php

test('application is healthy', function () {
    $response = $this->getJson('/up');

    $response->assertSuccessful();
});
