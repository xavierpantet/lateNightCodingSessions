<?php

interface Workflow {
    function input(Workflow $source);
    function name(): string;
    function content(): string;
}