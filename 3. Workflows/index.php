<?php

include_once "lib/Workflow.php";
include_once "lib/Input.php";

class HomeWorkflow implements Workflow {

    public function input(Workflow $source = null) {
        return '<a href="index.php">Go back to homepage</a>';
    }

    public function name(): string {
        return "Home";
    }

    function content(): string {
        $form = new DestinationWorkflow();

        return "Nice header<br />".$form->input($this)->display()."<br />Nice footer";
    }
};

class DestinationWorkflow implements Workflow {

    public function input(Workflow $source): Input {
        switch($source->name()) {
            case "Home":
                return new class() implements Input {
                    public function display(): string {
                        return '<a href="?page=destination">Click here to reach destination via link</a>';
                    }
                };

            default:
                return new class() implements Input {
                    public function display(): string {
                        return '<form action="?page=destination" method="POST"><input type="submit" value="Go to destination via form"/></form>';
                    }
                };
        }
    }

    public function name(): string {
        return "Destination";
    }

    public function content(): string {
        $home = new FinalWorkflow();
        return "Congrats! You have reached destination.<br />".$home->input($this)->display();
    }
}

class FinalWorkflow implements Workflow {

    public function input(Workflow $source): Input {
        return new class() implements Input {
            public function display(): string {
                return '<form action="?page=final" method="POST"><input type="submit" value="Go to final via form"/></form>';
            }
        };
    }

    public function name(): string {
        return "Final";
    }

    public function content(): string {
        return "Congrats! You have reached the last page.";
    }
}

$workflow = null;
switch($_GET['page']) {
    case null:
        $workflow = new HomeWorkflow();
        break;
    case "destination":
        $workflow = new DestinationWorkflow();
        break;
    case "final":
        $workflow = new FinalWorkflow();
        break;
    default:

}

if($workflow != null) {
    echo $workflow->content();
}
else {
    echo "Not Found";
}